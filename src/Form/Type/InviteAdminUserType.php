<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminInvitationPlugin\Form\Type;

use Calmfox\SyliusAdminInvitationPlugin\Invitation\PendingInvitation;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The inviting administrator types an e-mail address and nothing else. The account is
 * created disabled and without a password; the invitee fills in the rest.
 *
 * @extends AbstractType<AdminUserInterface>
 */
final class InviteAdminUserType extends AbstractType
{
    /** @param class-string<AdminUserInterface> $dataClass */
    public function __construct(
        private readonly string $dataClass,
        private readonly Security $security,
        private readonly string $defaultLocaleCode,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'sylius.form.user.email',
            ])
            ->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
                $adminUser = $event->getData();
                if (!$adminUser instanceof AdminUserInterface) {
                    return;
                }

                PendingInvitation::prepare($adminUser);

                // the panel opens in the inviter's language until the invitee changes it
                $inviter = $this->security->getUser();
                $adminUser->setLocaleCode(
                    $inviter instanceof AdminUserInterface && null !== $inviter->getLocaleCode()
                        ? $inviter->getLocaleCode()
                        : $this->defaultLocaleCode,
                );
            })
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => $this->dataClass,
            'validation_groups' => ['sylius'],
            // the username is the e-mail, so a taken username is reported on the e-mail field
            'error_mapping' => ['username' => 'email'],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'calmfox_admin_invitation_invite';
    }
}
