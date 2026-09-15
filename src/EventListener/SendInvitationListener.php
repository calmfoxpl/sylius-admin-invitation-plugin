<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminInvitationPlugin\EventListener;

use Calmfox\SyliusAdminInvitationPlugin\Invitation\InvitationSenderInterface;
use Psr\Log\LoggerInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;

/**
 * Listens to `sylius.admin_user.post_invite`, dispatched by the resource controller on the
 * invite route once the account is saved. A mail failure does not undo the account: the
 * success flash is swapped for an error telling how to resend.
 */
final readonly class SendInvitationListener
{
    public function __construct(
        private InvitationSenderInterface $invitationSender,
        private RequestStack $requestStack,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ResourceControllerEvent $event): void
    {
        $adminUser = $event->getSubject();
        if (!$adminUser instanceof AdminUserInterface) {
            return;
        }

        try {
            $this->invitationSender->send($adminUser);
        } catch (\Throwable $exception) {
            $this->logger->error('The administrator invitation could not be sent.', [
                'email' => $adminUser->getEmail(),
                'exception' => $exception,
            ]);

            $session = $this->requestStack->getSession();
            if ($session instanceof FlashBagAwareSessionInterface) {
                $session->getFlashBag()->get('success');
                $session->getFlashBag()->add('error', [
                    'message' => 'calmfox_admin_invitation.invitation.not_sent',
                    'parameters' => ['%email%' => $adminUser->getEmail()],
                ]);
            }
        }
    }
}
