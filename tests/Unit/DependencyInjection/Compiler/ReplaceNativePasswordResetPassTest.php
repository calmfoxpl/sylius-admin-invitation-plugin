<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusAdminInvitationPlugin\Unit\DependencyInjection\Compiler;

use Calmfox\SyliusAdminInvitationPlugin\DependencyInjection\Compiler\ReplaceNativePasswordResetPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class ReplaceNativePasswordResetPassTest extends TestCase
{
    private const NATIVE = ['sylius_admin.controller.account.render_reset_password_page', 'sylius_admin.controller.account.reset_password'];

    public function testItPointsTheNativeControllersAtThePluginPage(): void
    {
        $container = $this->container(true);

        (new ReplaceNativePasswordResetPass())->process($container);

        foreach (self::NATIVE as $id) {
            self::assertTrue($container->hasAlias($id), $id);
            self::assertSame('calmfox_admin_invitation.controller.reset_password', (string) $container->getAlias($id));
            self::assertTrue($container->getAlias($id)->isPublic());
        }
    }

    public function testItLeavesTheNativeControllersAloneWhenTurnedOff(): void
    {
        $container = $this->container(false);

        (new ReplaceNativePasswordResetPass())->process($container);

        foreach (self::NATIVE as $id) {
            self::assertTrue($container->hasDefinition($id), $id);
        }
    }

    private function container(bool $replace): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('calmfox_sylius_admin_invitation.replace_native_password_reset', $replace);
        foreach (self::NATIVE as $id) {
            $container->setDefinition($id, new Definition(\stdClass::class));
        }

        return $container;
    }
}
