<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusAdminInvitationPlugin\Unit\DependencyInjection;

use Calmfox\SyliusAdminInvitationPlugin\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testDefaults(): void
    {
        self::assertSame([
            'ttl' => 'P3D',
            'password_reset_ttl' => 'P1D',
            'replace_native_password_reset' => true,
            'firewall' => 'admin',
            'password' => [
                'min_length' => 12,
                'min_strength' => 3,
                'not_compromised' => false,
                'generated_length' => 20,
            ],
        ], $this->process([]));
    }

    public function testPasswordRulesCanBeTightened(): void
    {
        $password = $this->process(['password' => ['min_length' => 16, 'min_strength' => 4, 'not_compromised' => true]])['password'];

        self::assertIsArray($password);
        self::assertSame(16, $password['min_length']);
        self::assertSame(4, $password['min_strength']);
        self::assertTrue($password['not_compromised']);
    }

    public function testMinimumLengthCannotGoBelowEight(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process(['password' => ['min_length' => 6]]);
    }

    public function testStrengthScoreMustBeInRange(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process(['password' => ['min_strength' => 5]]);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<mixed>
     */
    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }
}
