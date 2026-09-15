<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminInvitationPlugin\Invitation;

use Sylius\Component\Core\Model\AdminUserInterface;

interface InvitationSenderInterface
{
    public function send(AdminUserInterface $adminUser): void;
}
