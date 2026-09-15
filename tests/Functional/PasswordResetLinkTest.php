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
