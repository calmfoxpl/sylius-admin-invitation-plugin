<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminInvitationPlugin\Form\Type;

use Calmfox\SyliusAdminInvitationPlugin\Password\PasswordPolicy;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/** @extends AbstractType<array{firstName: string|null, lastName: string|null, password?: string}> */
final class AcceptInvitationType extends AbstractType
{
    public function __construct(private readonly PasswordPolicy $passwordPolicy)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'sylius.form.user.first_name',
                'constraints' => [
                    new NotBlank(message: 'calmfox_admin_invitation.first_name.not_blank'),
                    new Length(max: 255, maxMessage: 'sylius.user.first_name.max'),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'sylius.form.user.last_name',
                'constraints' => [
                    new NotBlank(message: 'calmfox_admin_invitation.last_name.not_blank'),
                    new Length(max: 255, maxMessage: 'sylius.user.last_name.max'),
                ],
            ])
            ->add('password', RepeatedPasswordType::class, ['password_policy' => $this->passwordPolicy])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'messages',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'calmfox_admin_invitation_accept';
    }
}
