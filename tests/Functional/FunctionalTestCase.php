<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusAdminInvitationPlugin\Functional;

use Calmfox\SyliusAdminInvitationPlugin\Invitation\InvitationSenderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class FunctionalTestCase extends WebTestCase
{
    protected const STRONG_PASSWORD = 'kmv53p!L8za7KgjZh_AF';

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
    }

    protected function createAdmin(string $prefix = 'admin', bool $enabled = true, ?string $password = self::STRONG_PASSWORD): AdminUserInterface
    {
        $adminUser = $this->newAdmin();
        $email = sprintf('%s.%s@example.com', $prefix, bin2hex(random_bytes(4)));
        $adminUser->setEmail($email);
        $adminUser->setUsername($email);
        $adminUser->setLocaleCode('en_US');
        $adminUser->setEnabled($enabled);
        if (null !== $password) {
            $adminUser->setPlainPassword($password);
        }

        $this->entityManager()->persist($adminUser);
        $this->entityManager()->flush();

        return $adminUser;
    }

    protected function newAdmin(): AdminUserInterface
    {
        /** @var FactoryInterface<AdminUserInterface> $factory */
        $factory = $this->service('sylius.factory.admin_user', FactoryInterface::class);

        return $factory->createNew();
    }

    protected static function id(AdminUserInterface $adminUser): string
    {
        $id = $adminUser->getId();
        self::assertIsInt($id);

        return (string) $id;
    }

    protected function logInAs(AdminUserInterface $adminUser): void
    {
        $this->client->loginUser($adminUser, 'admin');
    }

    protected function reload(AdminUserInterface $adminUser): AdminUserInterface
    {
        $this->entityManager()->clear();
        $fresh = $this->adminUserRepository()->find($adminUser->getId());
        self::assertInstanceOf(AdminUserInterface::class, $fresh);

        return $fresh;
    }

    protected function findAdminByEmail(string $email): ?AdminUserInterface
    {
        $adminUser = $this->adminUserRepository()->findOneByEmail($email);

        return $adminUser instanceof AdminUserInterface ? $adminUser : null;
    }

    protected function isPasswordValid(AdminUserInterface $adminUser, string $password): bool
    {
        return $this->service('security.user_password_hasher', UserPasswordHasherInterface::class)->isPasswordValid($adminUser, $password);
    }

    protected function invitationSender(): InvitationSenderInterface
    {
        return $this->service('calmfox_admin_invitation.sender', InvitationSenderInterface::class);
    }

    protected function entityManager(): EntityManagerInterface
    {
        return $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
    }

    protected function lastEmail(): Email
    {
        $messages = self::getMailerMessages();
        self::assertNotEmpty($messages, 'No e-mail was sent.');
        $email = end($messages);
        self::assertInstanceOf(Email::class, $email);

        return $email;
    }

    protected function linkIn(Email $email, string $pathPattern): string
    {
        $html = (string) $email->getHtmlBody();
        self::assertSame(1, preg_match('#https?://[^"\s<>]*(' . $pathPattern . ')#', $html, $matches), 'The e-mail has no link matching ' . $pathPattern);

        return $matches[1];
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $type
     *
     * @return T
     */
    protected function service(string $id, string $type): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($type, $service);

        return $service;
    }

    /** @return UserRepositoryInterface<AdminUserInterface> */
    private function adminUserRepository(): UserRepositoryInterface
    {
        /** @var UserRepositoryInterface<AdminUserInterface> $repository */
        $repository = $this->service('sylius.repository.admin_user', UserRepositoryInterface::class);

        return $repository;
    }
}
