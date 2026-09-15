<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminInvitationPlugin\Event;

use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched once the invitee has set their name and password and is logged in. Listeners
 * may replace the response, e.g. to send the new administrator to a two-factor setup page
 * instead of the dashboard.
 */
final class InvitationAcceptedEvent extends Event
{
    public const NAME = 'calmfox_admin_invitation.invitation_accepted';

    public function __construct(
        private readonly AdminUserInterface $adminUser,
        private readonly Request $request,
        private Response $response,
    ) {
    }

    public function getAdminUser(): AdminUserInterface
    {
        return $this->adminUser;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getResponse(): Response
    {
        return $this->response;
    }

    public function setResponse(Response $response): void
    {
        $this->response = $response;
    }
}
