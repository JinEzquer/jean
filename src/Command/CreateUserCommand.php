<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CreateUserCommand extends Command
{
    protected static $defaultName = 'app:create-user';
    protected static $defaultDescription = 'Create a new admin user';

    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    protected function configure(): void
    {
        $this
            ->setName('app:create-user')
            ->setDescription('Creates a new admin user')
            ->addArgument('email', InputArgument::OPTIONAL, 'Email address')
            ->addArgument('password', InputArgument::OPTIONAL, 'Password')
            ->addOption('first-name', null, InputOption::VALUE_OPTIONAL, 'First name', 'Admin')
            ->addOption('last-name', null, InputOption::VALUE_OPTIONAL, 'Last name', 'User')
            ->addOption('role', null, InputOption::VALUE_OPTIONAL, 'User role (ROLE_ADMIN, ROLE_STAFF, ROLE_USER)', 'ROLE_ADMIN');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Admin User Creator');
        
        // Get email - from argument or ask interactively
        $email = $input->getArgument('email');
        if (!$email) {
            $email = $io->ask('Enter email', 'admin@example.com');
        }
        
        // Check if user already exists
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $io->error(sprintf('User with email %s already exists!', $email));
            return Command::FAILURE;
        }
        
        // Get password - from argument or ask interactively
        $password = $input->getArgument('password');
        if (!$password) {
            $password = $io->askHidden('Enter password (hidden)', null, function ($password) {
                if (empty($password)) {
                    throw new \RuntimeException('Password cannot be empty.');
                }
                if (strlen($password) < 6) {
                    throw new \RuntimeException('Password must be at least 6 characters long.');
                }
                return $password;
            });
        } elseif (strlen($password) < 6) {
            $io->error('Password must be at least 6 characters long.');
            return Command::FAILURE;
        }
        
        $firstName = $input->getOption('first-name') ?: $io->ask('Enter first name', 'Admin');
        $lastName = $input->getOption('last-name') ?: $io->ask('Enter last name', 'User');
        $role = $input->getOption('role');

        // Create the user
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setRoles([$role]);
        $user->setIsActive(true);
        
        // Hash the password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);
        
        // Save the user
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Successfully created %s user: %s', $role, $email));
        $io->note(sprintf('You can now log in with email: %s', $email));

        return Command::SUCCESS;
    }
}
