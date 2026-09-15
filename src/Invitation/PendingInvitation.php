<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminInvitationPlugin\Invitation;

use Sylius\Component\Core\Model\AdminUserInterface;

/**
 * An invited administrator is an account that nobody can log into yet: disabled and
 * without a password. Accepting the invitation sets the password and enables it.
 */
final class PendingInvitation
{
    public static function prepare(AdminUserInterface $adminUser): void
    {
        $adminUser->setUsername($adminUser->getEmail());
        $adminUser->setEnabled(false);
        $adminUser->setPassword(null);
        $adminUser->setPlainPassword(null);
    }

    public static function isPending(AdminUserInterface $adminUser): bool
    {
        return !$adminUser->isEnabled() && null === $adminUser->getPassword();
    }
}
