<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusAdminInvitationPlugin\Functional;

use Calmfox\SyliusAdminInvitationPlugin\Invitation\PendingInvitation;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class InviteAdminUserCommandTest extends FunctionalTestCase
{
    public function testItCreatesAndInvitesANewAdministrator(): void
    {
        $email = sprintf('first.%s@example.com', bin2hex(random_bytes(4)));

        $tester = $this->command();
        $tester->execute(['email' => $email, '--locale' => 'pl_PL']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $adminUser = $this->findAdminByEmail($email);
        self::assertNotNull($adminUser);
        self::assertTrue(PendingInvitation::isPending($adminUser));
        self::assertSame('pl_PL', $adminUser->getLocaleCode());
        self::assertEmailCount(1);
    }

    public function testItResendsAPendingInvitation(): void
    {
        $pending = $this->createAdmin('pending', false, null);

        $tester = $this->command();
        $tester->execute(['email' => $pending->getEmail()]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertEmailCount(1);
    }

    public function testItRefusesAnActiveAccount(): void
    {
        $active = $this->createAdmin('active');

        $tester = $this->command();
        $tester->execute(['email' => $active->getEmail()]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertEmailCount(0);
    }

    public function testItRefusesAnInvalidAddress(): void
    {
        $tester = $this->command();
        $tester->execute(['email' => 'not-an-email']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    private function command(): CommandTester
    {
        self::assertNotNull(self::$kernel);

        return new CommandTester((new Application(self::$kernel))->find('calmfox:admin:invite'));
    }
}
