<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusAdminInvitationPlugin\Functional;

use Calmfox\SyliusAdminInvitationPlugin\Invitation\PendingInvitation;
use Sylius\Component\Core\Model\AdminUserInterface;

final class AcceptInvitationTest extends FunctionalTestCase
{
    public function testTheInviteeSetsNameAndPasswordAndIsLoggedIn(): void
    {
        [$invitee, $path] = $this->invite();

        $crawler = $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-calmfox-password-generator]'), 'the password generator is offered');

        $this->client->submitForm('Save and log in', [
            'calmfox_admin_invitation_accept[firstName]' => 'Ada',
            'calmfox_admin_invitation_accept[lastName]' => 'Lovelace',
            'calmfox_admin_invitation_accept[password][first]' => self::STRONG_PASSWORD,
            'calmfox_admin_invitation_accept[password][second]' => self::STRONG_PASSWORD,
        ]);

        self::assertResponseRedirects('/admin/');
        $this->client->request('GET', '/admin/users/');
        self::assertResponseIsSuccessful('the invitee is logged in');

        $accepted = $this->reload($invitee);
        self::assertTrue($accepted->isEnabled());
        self::assertSame('Ada', $accepted->getFirstName());
        self::assertNull($accepted->getPasswordResetToken());
        self::assertTrue($this->isPasswordValid($accepted, self::STRONG_PASSWORD));
    }

    public function testAWeakPasswordIsRejected(): void
    {
        [$invitee, $path] = $this->invite();

        $this->client->request('GET', $path);
        $crawler = $this->client->submitForm('Save and log in', [
            'calmfox_admin_invitation_accept[firstName]' => 'Ada',
            'calmfox_admin_invitation_accept[lastName]' => 'Lovelace',
            'calmfox_admin_invitation_accept[password][first]' => 'password1234',
            'calmfox_admin_invitation_accept[password][second]' => 'password1234',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('too easy to guess', $crawler->text());
        self::assertTrue(PendingInvitation::isPending($this->reload($invitee)));
    }

    public function testTheLinkWorksOnlyOnce(): void
    {
        [, $path] = $this->invite();

        $this->client->request('GET', $path);
        $this->client->submitForm('Save and log in', [
            'calmfox_admin_invitation_accept[firstName]' => 'Ada',
            'calmfox_admin_invitation_accept[lastName]' => 'Lovelace',
            'calmfox_admin_invitation_accept[password][first]' => self::STRONG_PASSWORD,
            'calmfox_admin_invitation_accept[password][second]' => self::STRONG_PASSWORD,
        ]);
        $this->client->request('GET', '/admin/logout');

        $this->client->request('GET', $path);

        self::assertResponseRedirects('/admin/login');
    }

    public function testAnExpiredInvitationIsRefused(): void
    {
        [$invitee, $path] = $this->invite();
        $invitee->setPasswordRequestedAt(new \DateTime('-4 days'));
        $this->entityManager()->flush();

        $this->client->request('GET', $path);

        self::assertResponseRedirects('/admin/login');
    }

    /** @return array{AdminUserInterface, string} */
    private function invite(): array
    {
        $invitee = $this->newAdmin();
        $invitee->setEmail(sprintf('invitee.%s@example.com', bin2hex(random_bytes(4))));
        $invitee->setLocaleCode('en_US');
        PendingInvitation::prepare($invitee);
        $this->entityManager()->persist($invitee);
        $this->entityManager()->flush();

        $this->invitationSender()->send($invitee);

        return [$invitee, $this->linkIn($this->lastEmail(), '/admin/invitation/[A-Za-z0-9_~\-]+')];
    }
}
