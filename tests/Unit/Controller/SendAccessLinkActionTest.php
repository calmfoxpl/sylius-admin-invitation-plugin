<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusAdminInvitationPlugin\Unit\Controller;

use Calmfox\SyliusAdminInvitationPlugin\Controller\SendAccessLinkAction;
use Calmfox\SyliusAdminInvitationPlugin\Invitation\InvitationSenderInterface;
use Calmfox\SyliusAdminInvitationPlugin\Invitation\PasswordResetLinkSender;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\AdminUser;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Sylius\Component\User\Security\Generator\GeneratorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class SendAccessLinkActionTest extends TestCase
{
    public function testItRefusesARequestWithoutAValidCsrfToken(): void
    {
        $this->expectException(AccessDeniedHttpException::class);

        ($this->action(csrfValid: false))($this->request(), 1);
    }

    public function testAPendingAccountGetsTheInvitationAgain(): void
    {
        $adminUser = new AdminUser();
        $adminUser->setEmail('pending@example.com');

        $invitationSender = $this->createMock(InvitationSenderInterface::class);
        $invitationSender->expects(self::once())->method('send')->with($adminUser);

        ($this->action(adminUser: $adminUser, invitationSender: $invitationSender))($this->request(), 1);
    }

    #[DataProvider('unsafeRedirects')]
    public function testItNeverRedirectsOutsideTheApplication(string $redirect): void
    {
        $adminUser = new AdminUser();
        $adminUser->setEmail('pending@example.com');

        $response = ($this->action(adminUser: $adminUser))($this->request($redirect), 1);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/users/', $response->getTargetUrl());
    }

    /** @return iterable<string, array{string}> */
    public static function unsafeRedirects(): iterable
    {
        yield 'absolute URL' => ['https://evil.example/'];
        yield 'protocol-relative URL' => ['//evil.example/'];
        yield 'empty' => [''];
    }

    public function testItGoesBackToALocalPage(): void
    {
        $adminUser = new AdminUser();
        $adminUser->setEmail('pending@example.com');

        $response = ($this->action(adminUser: $adminUser))($this->request('/admin/users/1/edit'), 1);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/users/1/edit', $response->getTargetUrl());
    }

    private function action(
        bool $csrfValid = true,
        ?AdminUser $adminUser = null,
        ?InvitationSenderInterface $invitationSender = null,
    ): SendAccessLinkAction {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('find')->willReturn($adminUser);

        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn($csrfValid);

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/admin/users/');

        return new SendAccessLinkAction(
            $repository,
            $invitationSender ?? $this->createMock(InvitationSenderInterface::class),
            new PasswordResetLinkSender(
                $this->createMock(GeneratorInterface::class),
                $this->createMock(SenderInterface::class),
                $this->createMock(ObjectManager::class),
                $router,
                'en_US',
                'P1D',
            ),
            $csrf,
            $router,
            new NullLogger(),
        );
    }

    private function request(string $redirect = ''): Request
    {
        $request = new Request(request: ['_csrf_token' => 'token', '_redirect' => $redirect]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }
}
