<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusAdminInvitationPlugin\Unit\Invitation;

use Calmfox\SyliusAdminInvitationPlugin\Invitation\PendingInvitation;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdminUser;

final class PendingInvitationTest extends TestCase
{
    public function testItPreparesAnAccountNobodyCanLogInto(): void
    {
        $adminUser = new AdminUser();
        $adminUser->setEmail('new.admin@example.com');
        $adminUser->setEnabled(true);
        $adminUser->setPassword('hash');
        $adminUser->setPlainPassword('secret');

        PendingInvitation::prepare($adminUser);

        self::assertSame('new.admin@example.com', $adminUser->getUsername());
        self::assertFalse($adminUser->isEnabled());
        self::assertNull($adminUser->getPassword());
        self::assertNull($adminUser->getPlainPassword());
        self::assertTrue(PendingInvitation::isPending($adminUser));
    }

    public function testAnAccountWithAPasswordIsNotPending(): void
    {
        $disabledWithPassword = new AdminUser();
        $disabledWithPassword->setEnabled(false);
        $disabledWithPassword->setPassword('hash');

        $enabledWithoutPassword = new AdminUser();
        $enabledWithoutPassword->setEnabled(true);

        self::assertFalse(PendingInvitation::isPending($disabledWithPassword));
        self::assertFalse(PendingInvitation::isPending($enabledWithoutPassword));
    }
}
