<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminInvitationPlugin\Controller;

use Calmfox\SyliusAdminInvitationPlugin\Form\Type\ResetPasswordType;
use Calmfox\SyliusAdminInvitationPlugin\Invitation\PendingInvitation;
use Calmfox\SyliusAdminInvitationPlugin\Password\PasswordPolicy;
use Doctrine\Persistence\ObjectManager;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Sylius\Component\User\Security\PasswordUpdaterInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * The page behind a password reset link — sent by another administrator, or requested with
 * "Forgot password?" (it replaces Sylius' native page, see ReplaceNativePasswordResetPass). The new password
 * follows the same policy as at invitation. Afterwards the administrator logs in the usual
 * way — so a second factor, if the account has one, is still asked for.
 */
final readonly class ResetPasswordAction
{
    /** @param UserRepositoryInterface<AdminUserInterface> $adminUserRepository */
    public function __construct(
        private UserRepositoryInterface $adminUserRepository,
        private ObjectManager $adminUserManager,
        private PasswordUpdaterInterface $passwordUpdater,
        private PasswordPolicy $passwordPolicy,
        private FormFactoryInterface $formFactory,
        private UrlGeneratorInterface $urlGenerator,
        private Environment $twig,
        private string $ttl,
    ) {
    }

    public function __invoke(Request $request, string $token): Response
    {
        $adminUser = $this->adminUserRepository->findOneBy(['passwordResetToken' => $token]);

        if (
            !$adminUser instanceof AdminUserInterface ||
            PendingInvitation::isPending($adminUser) ||
            !$adminUser->isPasswordRequestNonExpired(new \DateInterval($this->ttl))
        ) {
            $this->flash($request, 'error', 'calmfox_admin_invitation.password_reset.invalid');

            return new RedirectResponse($this->urlGenerator->generate('sylius_admin_login'));
        }

        $form = $this->formFactory->create(ResetPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $password */
            $password = $form->get('password')->getData();
            $adminUser->setPlainPassword($password);
            $this->passwordUpdater->updatePassword($adminUser);
            $adminUser->setPasswordResetToken(null);
            $adminUser->setPasswordRequestedAt(null);
            $this->adminUserManager->flush();

            $this->flash($request, 'success', 'calmfox_admin_invitation.password_reset.done');

            return new RedirectResponse($this->urlGenerator->generate('sylius_admin_login'));
        }

        return new Response(
            $this->twig->render('@CalmfoxSyliusAdminInvitationPlugin/password_reset.html.twig', [
                'form' => $form->createView(),
                'adminUser' => $adminUser,
                'token' => $token,
                'generatedPasswordLength' => $this->passwordPolicy->generatedLength(),
            ]),
            $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK,
        );
    }

    private function flash(Request $request, string $type, string $message): void
    {
        $session = $request->hasSession() ? $request->getSession() : null;
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($type, $message);
        }
    }
}
