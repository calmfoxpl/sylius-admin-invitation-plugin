<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusAdminInvitationPlugin\Functional;

use Calmfox\SyliusAdminInvitationPlugin\Invitation\PendingInvitation;

final class InviteAdministratorTest extends FunctionalTestCase
{
    public function testTheAdministratorsListLinksToTheInvitation(): void
    {
        $this->logInAs($this->createAdmin());

        $crawler = $this->client->request('GET', '/admin/users/');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('a[href$="/admin/users/invite"]'));
    }

    public function testAnInvitationCreatesAPendingAccountAndSendsTheLink(): void
    {
        $this->logInAs($this->createAdmin());
        $email = sprintf('invitee.%s@example.com', bin2hex(random_bytes(4)));

        $this->client->request('GET', '/admin/users/invite');
        self::assertResponseIsSuccessful();
        $this->client->submitForm('Send invitation', ['calmfox_admin_invitation_invite[email]' => $email]);

        self::assertResponseRedirects('/admin/users/');
        self::assertEmailCount(1);
        $message = $this->lastEmail();
        self::assertSame($email, $message->getTo()[0]->getAddress());
        self::assertSame('Invitation to the administration panel', $message->getSubject());
        $this->linkIn($message, '/admin/invitation/[A-Za-z0-9_~\-]+');

        $invitee = $this->findAdminByEmail($email);
        self::assertNotNull($invitee);
        self::assertTrue(PendingInvitation::isPending($invitee));
        self::assertSame($email, $invitee->getUsername());
        self::assertNotNull($invitee->getPasswordResetToken());
    }

    public function testATakenAddressIsReportedOnTheForm(): void
    {
        $existing = $this->createAdmin('existing');
        $this->logInAs($this->createAdmin());

        $this->client->request('GET', '/admin/users/invite');
        $this->client->submitForm('Send invitation', ['calmfox_admin_invitation_invite[email]' => $existing->getEmail()]);

        self::assertResponseStatusCodeSame(422);
        self::assertEmailCount(0);
    }

    public function testTheInvitationPageIsForAdministratorsOnly(): void
    {
        $this->client->request('GET', '/admin/users/invite');

        self::assertResponseRedirects('/admin/login');
    }
}
