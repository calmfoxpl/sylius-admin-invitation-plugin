<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusAdminInvitationPlugin\Unit\Password;

use Calmfox\SyliusAdminInvitationPlugin\Password\PasswordPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\PasswordStrength;
use Symfony\Component\Validator\Validation;

final class PasswordPolicyTest extends TestCase
{
    public function testItBuildsItselfFromConfiguration(): void
    {
        $policy = PasswordPolicy::fromConfig(['min_length' => 14, 'min_strength' => 4, 'not_compromised' => false, 'generated_length' => 24]);

        self::assertSame(14, $policy->minLength());
        self::assertSame(24, $policy->generatedLength());

        $length = self::constraintOf($policy, Length::class);
        self::assertSame(14, $length->min);
        self::assertSame(4, self::constraintOf($policy, PasswordStrength::class)->minScore);
    }

    public function testItChecksBreachesOnlyWhenAskedTo(): void
    {
        self::assertNull(self::findConstraint(new PasswordPolicy(12, 3, false, 20), NotCompromisedPassword::class));
        self::assertNotNull(self::findConstraint(new PasswordPolicy(12, 3, true, 20), NotCompromisedPassword::class));
    }

    #[DataProvider('weakPasswords')]
    public function testItRejectsWeakPasswords(string $password): void
    {
        $violations = Validation::createValidator()->validate($password, (new PasswordPolicy(12, 3, false, 20))->constraints());

        self::assertGreaterThan(0, $violations->count());
    }

    /** @return iterable<string, array{string}> */
    public static function weakPasswords(): iterable
    {
        yield 'empty' => [''];
        yield 'too short' => ['Ab1!xyz'];
        yield 'long but guessable' => ['password1234'];
        yield 'repeated characters' => ['aaaaaaaaaaaaaaaa'];
    }

    public function testItAcceptsAStrongPassword(): void
    {
        $violations = Validation::createValidator()->validate('kmv53p!L8za7KgjZh_AF', (new PasswordPolicy(12, 3, false, 20))->constraints());

        self::assertCount(0, $violations);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private static function constraintOf(PasswordPolicy $policy, string $class): object
    {
        $constraint = self::findConstraint($policy, $class);
        self::assertInstanceOf($class, $constraint);

        return $constraint;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    private static function findConstraint(PasswordPolicy $policy, string $class): ?object
    {
        foreach ($policy->constraints() as $constraint) {
            if ($constraint instanceof $class) {
                return $constraint;
            }
        }

        return null;
    }
}
