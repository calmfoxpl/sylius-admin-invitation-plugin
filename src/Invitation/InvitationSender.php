<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminInvitationPlugin\Invitation;

use Doctrine\Persistence\ObjectManager;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Sylius\Component\User\Security\Generator\GeneratorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Stores a fresh token on the invited administrator and e-mails them the link to the
 * acceptance page. The token lives in Sylius' own password reset fields, so the plugin
 * needs no schema changes.
 */
final readonly class InvitationSender implements InvitationSenderInterface
{
    public const EMAIL_CODE = 'calmfox_admin_invitation';

    public function __construct(
        private GeneratorInterface $tokenGenerator,
        private SenderInterface $emailSender,
        private ObjectManager $adminUserManager,
        private UrlGeneratorInterface $urlGenerator,
        private string $defaultLocaleCode,
    ) {
    }

    public function send(AdminUserInterface $adminUser): void
    {
        $email = $adminUser->getEmail();
        if (null === $email || '' === $email) {
            throw new \InvalidArgumentException('An administrator without an e-mail address cannot be invited.');
        }

        $token = $this->tokenGenerator->generate();
        $adminUser->setPasswordResetToken($token);
        $adminUser->setPasswordRequestedAt(new \DateTime());
        $this->adminUserManager->flush();

        $this->emailSender->send(self::EMAIL_CODE, [$email], [
            'adminUser' => $adminUser,
            'localeCode' => $adminUser->getLocaleCode() ?? $this->defaultLocaleCode,
            'invitationUrl' => $this->urlGenerator->generate(
                'calmfox_admin_invitation_accept',
                ['token' => $token],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
        ]);
    }
}
