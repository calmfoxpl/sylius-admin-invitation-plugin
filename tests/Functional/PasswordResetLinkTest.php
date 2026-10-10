<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusAdminInvitationPlugin\Functional;

final class PasswordResetLinkTest extends FunctionalTestCase
{
    public function testAnotherAdministratorSendsALinkToSetANewPassword(): void
    {
        $target = $this->createAdmin('target');
        $this->logInAs($this->createAdmin());

        $crawler = $this->client->request('GET', '/admin/users/' . self::id($target) . '/edit');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Account access', $crawler->text());

        $form = $crawler->filter('form#calmfox-admin-access-link')->form();
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/users/' . self::id($target) . '/edit');
        $message = $this->lastEmail();
        self::assertSame($target->getEmail(), $message->getTo()[0]->getAddress());
        $path = $this->linkIn($message, '/admin/forgotten-password/link/[A-Za-z0-9_~\-]+');

        $this->client->request('GET', '/admin/logout');
        $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();

        $crawler = $this->client->submitForm('Save new password', [
            'calmfox_admin_invitation_password_reset[password][first]' => 'password1234',
            'calmfox_admin_invitation_password_reset[password][second]' => 'password1234',
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('too easy to guess', $crawler->text());

        $this->client->submitForm('Save new password', [
            'calmfox_admin_invitation_password_reset[password][first]' => 'N3w!pass-' . bin2hex(random_bytes(6)),
            'calmfox_admin_invitation_password_reset[password][second]' => 'mismatch',
        ]);
        self::assertResponseStatusCodeSame(422);

        $newPassword = 'N3w!pass-' . bin2hex(random_bytes(6));
        $this->client->submitForm('Save new password', [
            'calmfox_admin_invitation_password_reset[password][first]' => $newPassword,
            'calmfox_admin_invitation_password_reset[password][second]' => $newPassword,
        ]);
        self::assertResponseRedirects('/admin/login', null, 'no automatic login: a second factor may still be required');

        $updated = $this->reload($target);
        self::assertTrue($this->isPasswordValid($updated, $newPassword));
        self::assertNull($updated->getPasswordResetToken());

        $this->client->request('GET', $path);
        self::assertResponseRedirects('/admin/login', null, 'the link works only once');
    }

    /**
     * Behind a reverse proxy that mounts the shop under a prefix (`X-Forwarded-Prefix: /store`),
     * the forms must carry the page's full address: a bare request path would lose the prefix
     * and the browser would land on whatever else lives at the root of the domain.
     */
    public function testBehindAPrefixTheFormsKeepTheFullAddress(): void
    {
        $prefix = ['HTTP_X_FORWARDED_PREFIX' => '/store'];
        $target = $this->createAdmin('target');
        $this->logInAs($this->createAdmin());

        $crawler = $this->client->request('GET', '/admin/users/', [], [], $prefix);
        self::assertResponseIsSuccessful();
        self::assertSame(
            'http://localhost/store/admin/users/',
            $crawler->filter('form[action="/store/admin/users/' . self::id($target) . '/access-link"] input[name="_redirect"]')->attr('value'),
        );

        $crawler = $this->client->request('GET', '/admin/users/' . self::id($target) . '/edit', [], [], $prefix);
        $form = $crawler->filter('form#calmfox-admin-access-link');
        self::assertSame('http://localhost/store/admin/users/' . self::id($target) . '/edit', $form->filter('input[name="_redirect"]')->attr('value'));

        // the proxy strips the prefix before the request reaches the application
        $this->client->request('POST', '/admin/users/' . self::id($target) . '/access-link', [
            '_csrf_token' => $form->filter('input[name="_csrf_token"]')->attr('value'),
            '_redirect' => $form->filter('input[name="_redirect"]')->attr('value'),
        ], [], $prefix);
        self::assertResponseRedirects('http://localhost/store/admin/users/' . self::id($target) . '/edit');

        $link = $this->linkIn($this->lastEmail(), '/admin/forgotten-password/link/[A-Za-z0-9_~\-]+');
        $this->client->request('GET', '/admin/logout');
        $crawler = $this->client->request('GET', $link, [], [], $prefix);
        self::assertResponseIsSuccessful();
        self::assertSame('http://localhost/store' . $link, $crawler->filter('form[name="calmfox_admin_invitation_password_reset"]')->attr('action'));
    }

    public function testAPendingAccountGetsItsInvitationAgain(): void
    {
        $pending = $this->createAdmin('pending', false, null);
        $this->logInAs($this->createAdmin());

        $crawler = $this->client->request('GET', '/admin/users/' . self::id($pending) . '/edit');
        $this->client->submit($crawler->filter('form#calmfox-admin-access-link')->form());

        $this->linkIn($this->lastEmail(), '/admin/invitation/[A-Za-z0-9_~\-]+');
    }

    public function testTheLinkCannotBeSentWithoutACsrfToken(): void
    {
        $target = $this->createAdmin('target');
        $this->logInAs($this->createAdmin());

        $this->client->request('POST', '/admin/users/' . self::id($target) . '/access-link', ['_csrf_token' => 'forged']);

        self::assertResponseStatusCodeSame(403);
        self::assertEmailCount(0);
    }

    public function testTheNativeForgottenPasswordLinkOpensThePageWithThePasswordPolicy(): void
    {
        $target = $this->createAdmin('forgetful');

        $this->client->request('GET', '/admin/forgotten-password');
        self::assertResponseIsSuccessful();
        $this->client->submitForm('Reset', ['sylius_admin_request_password_reset[email]' => $target->getEmail()]);

        self::assertEmailCount(1);
        // Sylius builds the link in this e-mail from the channel hostname; the test application has
        // no channel, so the e-mail carries the token only — open the native address directly
        $token = $this->reload($target)->getPasswordResetToken();
        self::assertNotNull($token);
        $path = '/admin/forgotten-password/' . $token;

        $crawler = $this->client->request('GET', $path);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-calmfox-password-generator]'));

        $crawler = $this->client->submitForm('Save new password', [
            'calmfox_admin_invitation_password_reset[password][first]' => 'password1234',
            'calmfox_admin_invitation_password_reset[password][second]' => 'password1234',
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('too easy to guess', $crawler->text());
    }
}
