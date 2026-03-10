<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:update-admin-password',
    description: 'Updates the admin password',
    hidden: false,
    aliases: ['app:admin:update-password']
)]

class UpdateAdminPasswordCommand extends Command
{
    private $entityManager;
    private $passwordHasher;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    protected function configure()
    {
        $this
            ->addArgument('new_password', InputArgument::REQUIRED, 'The new password for the admin account')
            ->setHelp('This command allows you to update the admin password. Usage: php bin/console app:update-admin-password "newpassword"');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $newPassword = $input->getArgument('new_password');
        
        if (strlen($newPassword) < 6) {
            $output->writeln('<error>Error: Password must be at least 6 characters long</error>');
            return Command::FAILURE;
        }
        
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@example.com']);
        
        if (!$user) {
            $output->writeln('<error>Error: Admin user not found!</error>');
            return Command::FAILURE;
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $this->entityManager->flush();

        $output->writeln('<info>Success: Admin password has been updated</info>');
        return Command::SUCCESS;
    }
}
