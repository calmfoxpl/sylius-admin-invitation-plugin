<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminInvitationPlugin;

use Calmfox\SyliusAdminInvitationPlugin\DependencyInjection\Compiler\ReplaceNativePasswordResetPass;
use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class CalmfoxSyliusAdminInvitationPlugin extends Bundle
{
    use SyliusPluginTrait;

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new ReplaceNativePasswordResetPass());
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
