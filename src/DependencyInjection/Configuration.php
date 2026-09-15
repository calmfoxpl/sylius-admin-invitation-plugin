<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminInvitationPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('calmfox_sylius_admin_invitation');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('ttl')
                    ->info('How long an invitation link stays valid (ISO 8601 interval).')
                    ->defaultValue('P3D')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('password_reset_ttl')
                    ->info('How long a password reset link sent by another administrator stays valid (ISO 8601 interval).')
                    ->defaultValue('P1D')
                    ->cannotBeEmpty()
                ->end()
                ->booleanNode('replace_native_password_reset')
                    ->info('Open the link from Sylius\' own "Forgot password?" e-mail on the plugin\'s page, which applies the password policy.')
                    ->defaultTrue()
                ->end()
                ->scalarNode('firewall')
                    ->info('Firewall the invitee gets logged into after accepting.')
                    ->defaultValue('admin')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('password')
                    ->info('Requirements for the password the invitee sets.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('min_length')->defaultValue(12)->min(8)->end()
                        ->integerNode('min_strength')
                            ->info('Minimum PasswordStrength score: 1 weak, 2 medium, 3 strong, 4 very strong.')
                            ->defaultValue(3)->min(1)->max(4)
                        ->end()
                        ->booleanNode('not_compromised')
                            ->info('Reject passwords found in data breaches (asks the haveibeenpwned.com API).')
                            ->defaultFalse()
                        ->end()
                        ->integerNode('generated_length')
                            ->info('Length of the password suggested by the "Generate" button.')
                            ->defaultValue(20)->min(12)->max(64)
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
