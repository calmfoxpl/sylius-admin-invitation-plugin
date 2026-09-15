<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminInvitationPlugin\Controller;

use Calmfox\SyliusAdminInvitationPlugin\Event\InvitationAcceptedEvent;
use Calmfox\SyliusAdminInvitationPlugin\Form\Type\AcceptInvitationType;
use Calmfox\SyliusAdminInvitationPlugin\Invitation\PendingInvitation;
use Calmfox\SyliusAdminInvitationPlugin\Password\PasswordPolicy;
use Doctrine\Persistence\ObjectManager;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Sylius\Component\User\Security\PasswordUpdaterInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;

/**
 * The page behind the link in the invitation e-mail: the invitee enters their name and
 * password, the account gets enabled and they land in the panel already logged in.
 */
final readonly class AcceptInvitationAction
{
    /** @param UserRepositoryInterface<AdminUserInterface> $adminUserRepository */
    public function __construct(
        private UserRepositoryInterface $adminUserRepository,
        private ObjectManager $adminUserManager,
        private PasswordUpdaterInterface $passwordUpdater,
        private FormFactoryInterface $formFactory,
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
        private Environment $twig,
        private EventDispatcherInterface $eventDispatcher,
        private PasswordPolicy $passwordPolicy,
        private string $ttl,
        private string $firewallName,
    ) {
    }

    public function __invoke(Request $request, string $token): Response
    {
        $adminUser = $this->adminUserRepository->findOneBy(['passwordResetToken' => $token]);

        if (
            !$adminUser instanceof AdminUserInterface ||
            !PendingInvitation::isPending($adminUser) ||
            !$adminUser->isPasswordRequestNonExpired(new \DateInterval($this->ttl))
        ) {
            $this->addFlash($request, 'error', 'calmfox_admin_invitation.invitation.invalid');

            return new RedirectResponse($this->urlGenerator->generate('sylius_admin_login'));
        }

        $form = $this->formFactory->create(AcceptInvitationType::class, [
            'firstName' => $adminUser->getFirstName(),
            'lastName' => $adminUser->getLastName(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{firstName: string, lastName: string, password: string} $data */
            $data = $form->getData();

            $adminUser->setFirstName($data['firstName']);
            $adminUser->setLastName($data['lastName']);
            $adminUser->setPlainPassword($data['password']);
            $this->passwordUpdater->updatePassword($adminUser);
            $adminUser->setEnabled(true);
            $adminUser->setPasswordResetToken(null);
            $adminUser->setPasswordRequestedAt(null);
            $this->adminUserManager->flush();

            $this->security->login($adminUser, 'form_login', $this->firewallName);
            $this->addFlash($request, 'success', 'calmfox_admin_invitation.invitation.accepted');

            $event = new InvitationAcceptedEvent(
                $adminUser,
                $request,
                new RedirectResponse($this->urlGenerator->generate('sylius_admin_dashboard')),
            );
            $this->eventDispatcher->dispatch($event, InvitationAcceptedEvent::NAME);

            return $event->getResponse();
        }

        return new Response(
            $this->twig->render('@CalmfoxSyliusAdminInvitationPlugin/accept_invitation.html.twig', [
                'form' => $form->createView(),
                'adminUser' => $adminUser,
                'token' => $token,
                'generatedPasswordLength' => $this->passwordPolicy->generatedLength(),
            ]),
            $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK,
        );
    }

    private function addFlash(Request $request, string $type, string $message): void
    {
        $session = $request->hasSession() ? $request->getSession() : null;
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($type, $message);
        }
    }
}
