<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminInvitationPlugin\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The link from Sylius' own "Forgot password?" e-mail (/admin/forgotten-password/{token}) opens
 * the plugin's page instead of the native one: same token, but the new password has to meet
 * the password policy and the generator is there. Done on the controllers rather than the
 * routes, so it does not depend on the order routes are imported in.
 */
final class ReplaceNativePasswordResetPass implements CompilerPassInterface
{
    private const NATIVE_CONTROLLERS = [
        'sylius_admin.controller.account.render_reset_password_page', // GET
        'sylius_admin.controller.account.reset_password',              // POST
    ];

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('calmfox_sylius_admin_invitation.replace_native_password_reset') ||
            true !== $container->getParameter('calmfox_sylius_admin_invitation.replace_native_password_reset')) {
            return;
        }

        foreach (self::NATIVE_CONTROLLERS as $id) {
            if ($container->hasDefinition($id) || $container->hasAlias($id)) {
                $container->removeDefinition($id);
                $container->setAlias($id, 'calmfox_admin_invitation.controller.reset_password')->setPublic(true);
            }
        }
    }
}
