<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminInvitationPlugin\Controller;

use Calmfox\SyliusAdminInvitationPlugin\Invitation\InvitationSenderInterface;
use Calmfox\SyliusAdminInvitationPlugin\Invitation\PasswordResetLinkSender;
use Calmfox\SyliusAdminInvitationPlugin\Invitation\PendingInvitation;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * One button for "let this person back in": an account that never accepted its invitation
 * gets the invitation again, an active one gets a link to set a new password.
 */
final readonly class SendAccessLinkAction
{
    public const CSRF_TOKEN_ID = 'calmfox_admin_access_link';

    /** @param UserRepositoryInterface<AdminUserInterface> $adminUserRepository */
    public function __construct(
        private UserRepositoryInterface $adminUserRepository,
        private InvitationSenderInterface $invitationSender,
        private PasswordResetLinkSender $passwordResetLinkSender,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request, int|string $id): Response
    {
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID . $id, (string) $request->request->get('_csrf_token')))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $adminUser = $this->adminUserRepository->find($id);
        if (!$adminUser instanceof AdminUserInterface) {
            throw new NotFoundHttpException();
        }

        $pending = PendingInvitation::isPending($adminUser);

        try {
            $pending ? $this->invitationSender->send($adminUser) : $this->passwordResetLinkSender->send($adminUser);
            $this->flash($request, 'success', $pending ? 'calmfox_admin_invitation.access_link.invitation_resent' : 'calmfox_admin_invitation.access_link.reset_sent', $adminUser);
        } catch (\Throwable $exception) {
            $this->logger->error('The administrator access link could not be sent.', ['email' => $adminUser->getEmail(), 'exception' => $exception]);
            $this->flash($request, 'error', 'calmfox_admin_invitation.access_link.not_sent', $adminUser);
        }

        return new RedirectResponse($this->redirectTarget($request));
    }

    private function flash(Request $request, string $type, string $message, AdminUserInterface $adminUser): void
    {
        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($type, ['message' => $message, 'parameters' => ['%email%' => $adminUser->getEmail()]]);
        }
    }

    /** Back where the button was (list or edit page); only local paths, never another site. */
    private function redirectTarget(Request $request): string
    {
        $target = (string) $request->request->get('_redirect');

        return str_starts_with($target, '/') && !str_starts_with($target, '//')
            ? $target
            : $this->urlGenerator->generate('sylius_admin_admin_user_index');
    }
}
