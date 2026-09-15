<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminInvitationPlugin\Password;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\PasswordStrength;

/**
 * What an administrator's password must look like. Length alone lets "aaaaaaaaaaaa" through,
 * so on top of it goes Symfony's entropy estimate (PasswordStrength) and, optionally, a check
 * against known data breaches.
 */
final readonly class PasswordPolicy
{
    /**
     * @param int<8, max>   $minLength
     * @param 1|2|3|4       $minStrength
     * @param int<12, 64>   $generatedLength
     */
    public function __construct(
        private int $minLength,
        private int $minStrength,
        private bool $notCompromised,
        private int $generatedLength,
    ) {
    }

    /** @param array{min_length: int<8, max>, min_strength: 1|2|3|4, not_compromised: bool, generated_length: int<12, 64>} $config */
    public static function fromConfig(array $config): self
    {
        return new self($config['min_length'], $config['min_strength'], $config['not_compromised'], $config['generated_length']);
    }

    /** @return list<Constraint> */
    public function constraints(): array
    {
        $constraints = [
            new NotBlank(message: 'sylius.user.plainPassword.not_blank'),
            new Length(min: $this->minLength, max: 4096, minMessage: 'sylius.user.password.min', maxMessage: 'sylius.user.password.max'),
            new PasswordStrength(minScore: $this->minStrength, message: 'calmfox_admin_invitation.password.too_weak'),
        ];

        if ($this->notCompromised) {
            $constraints[] = new NotCompromisedPassword(message: 'calmfox_admin_invitation.password.compromised');
        }

        return $constraints;
    }

    public function minLength(): int
    {
        return $this->minLength;
    }

    public function generatedLength(): int
    {
        return $this->generatedLength;
    }
}
