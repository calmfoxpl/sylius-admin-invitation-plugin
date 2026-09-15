<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminInvitationPlugin\Form\Type;

use Calmfox\SyliusAdminInvitationPlugin\Password\PasswordPolicy;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** New password typed twice, checked against the password policy. Shared by invitation and reset. *
 * @extends AbstractType<string>
 */
final class RepeatedPasswordType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired('password_policy')
            ->setAllowedTypes('password_policy', PasswordPolicy::class)
            ->setDefaults([
                'type' => PasswordType::class,
                'invalid_message' => 'sylius.user.plainPassword.mismatch',
                'second_options' => ['label' => 'sylius.form.user_reset_password.confirmation'],
            ])
            ->setNormalizer('first_options', static fn (Options $options): array => [
                'label' => 'sylius.form.user_reset_password.new',
                'help' => 'calmfox_admin_invitation.ui.password_requirements',
                'help_translation_parameters' => ['%min_length%' => self::policy($options)->minLength()],
            ])
            ->setNormalizer('constraints', static fn (Options $options): array => self::policy($options)->constraints())
        ;
    }

    public function getParent(): string
    {
        return RepeatedType::class;
    }

    /** @param Options<array<string, mixed>> $options */
    private static function policy(Options $options): PasswordPolicy
    {
        $policy = $options['password_policy'];
        \assert($policy instanceof PasswordPolicy);

        return $policy;
    }
}
