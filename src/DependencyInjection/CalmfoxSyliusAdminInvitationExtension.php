<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminInvitationPlugin\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class CalmfoxSyliusAdminInvitationExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{ttl: string, password_reset_ttl: string, replace_native_password_reset: bool, firewall: string, password: array<string, bool|int>} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('calmfox_sylius_admin_invitation.ttl', $config['ttl']);
        $container->setParameter('calmfox_sylius_admin_invitation.firewall', $config['firewall']);
        $container->setParameter('calmfox_sylius_admin_invitation.password_reset_ttl', $config['password_reset_ttl']);
        $container->setParameter('calmfox_sylius_admin_invitation.replace_native_password_reset', $config['replace_native_password_reset']);
        $container->setParameter('calmfox_sylius_admin_invitation.password', $config['password']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.yaml');
    }
}
