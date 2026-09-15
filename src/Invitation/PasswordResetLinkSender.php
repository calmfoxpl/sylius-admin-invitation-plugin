<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminInvitationPlugin\Invitation;

use Doctrine\Persistence\ObjectManager;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Sylius\Component\User\Security\Generator\GeneratorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Another administrator sends a link with which this one sets a new password themselves —
 * nobody learns or dictates the new password. The token lives in Sylius' password reset fields.
 */
final readonly class PasswordResetLinkSender
{
    public const EMAIL_CODE = 'calmfox_admin_password_reset';

    public function __construct(
        private GeneratorInterface $tokenGenerator,
        private SenderInterface $emailSender,
        private ObjectManager $adminUserManager,
        private UrlGeneratorInterface $urlGenerator,
        private string $defaultLocaleCode,
        private string $ttl,
    ) {
    }

    public function send(AdminUserInterface $adminUser): void
    {
        $email = $adminUser->getEmail();
        if (null === $email || '' === $email) {
            throw new \InvalidArgumentException('An administrator without an e-mail address cannot get a password reset link.');
        }

        $token = $this->tokenGenerator->generate();
        $adminUser->setPasswordResetToken($token);
        $adminUser->setPasswordRequestedAt(new \DateTime());
        $this->adminUserManager->flush();

        $validUntil = (new \DateTimeImmutable())->add(new \DateInterval($this->ttl));

        $this->emailSender->send(self::EMAIL_CODE, [$email], [
            'adminUser' => $adminUser,
            'localeCode' => $adminUser->getLocaleCode() ?? $this->defaultLocaleCode,
            'resetUrl' => $this->urlGenerator->generate(
                'calmfox_admin_invitation_password_reset',
                ['token' => $token],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
            'validUntil' => $validUntil,
        ]);
    }
}
