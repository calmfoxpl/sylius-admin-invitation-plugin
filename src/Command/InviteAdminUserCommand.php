<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminInvitationPlugin\Command;

use Calmfox\SyliusAdminInvitationPlugin\Invitation\InvitationSenderInterface;
use Calmfox\SyliusAdminInvitationPlugin\Invitation\PendingInvitation;
use Doctrine\Persistence\ObjectManager;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Same as the "Invite" button in the panel: for the first administrator of a fresh
 * installation, and to resend an invitation that got lost or expired.
 */
#[AsCommand(
    name: 'calmfox:admin:invite',
    description: 'Invites an administrator by e-mail; they set their own name and password.',
)]
final class InviteAdminUserCommand extends Command
{
    /**
     * @param UserRepositoryInterface<AdminUserInterface> $adminUserRepository
     * @param FactoryInterface<AdminUserInterface> $adminUserFactory
     */
    public function __construct(
        private readonly InvitationSenderInterface $invitationSender,
        private readonly UserRepositoryInterface $adminUserRepository,
        private readonly FactoryInterface $adminUserFactory,
        private readonly ObjectManager $adminUserManager,
        private readonly ValidatorInterface $validator,
        private readonly string $defaultLocaleCode,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'E-mail address of the administrator')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Panel and e-mail locale (defaults to the application locale for new accounts)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = trim(self::stringInput($input->getArgument('email')));

        if (0 < \count($this->validator->validate($email, new Email(mode: Email::VALIDATION_MODE_STRICT)))) {
            $io->error(sprintf('"%s" is not a valid e-mail address.', $email));

            return Command::FAILURE;
        }

        $adminUser = $this->adminUserRepository->findOneByEmail($email);

        if (null === $adminUser) {
            /** @var AdminUserInterface $adminUser */
            $adminUser = $this->adminUserFactory->createNew();
            $adminUser->setEmail($email);
            $adminUser->setLocaleCode($this->defaultLocaleCode);
            PendingInvitation::prepare($adminUser);

            $this->adminUserManager->persist($adminUser);
            $this->adminUserManager->flush();
        } elseif (!$adminUser instanceof AdminUserInterface || !PendingInvitation::isPending($adminUser)) {
            $io->error(sprintf('%s already has an active account. They can use "Forgot password?" on the login page.', $email));

            return Command::FAILURE;
        }

        $locale = self::stringInput($input->getOption('locale'));
        if ('' !== $locale) {
            $adminUser->setLocaleCode($locale);
        }

        $this->invitationSender->send($adminUser);
        $io->success(sprintf('Invitation sent to %s.', $email));

        return Command::SUCCESS;
    }

    private static function stringInput(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
