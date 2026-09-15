<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminInvitationPlugin\Form\Type;

use Calmfox\SyliusAdminInvitationPlugin\Password\PasswordPolicy;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

/** @extends AbstractType<array{password: string}> */
final class ResetPasswordType extends AbstractType
{
    public function __construct(private readonly PasswordPolicy $passwordPolicy)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('password', RepeatedPasswordType::class, ['password_policy' => $this->passwordPolicy]);
    }

    public function getBlockPrefix(): string
    {
        return 'calmfox_admin_invitation_password_reset';
    }
}
