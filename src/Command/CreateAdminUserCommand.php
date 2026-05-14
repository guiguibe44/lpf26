<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Crée un compte administrateur ou ajoute ROLE_ADMIN à un compte existant.',
)]
final class CreateAdminUserCommand extends Command
{
    private const MIN_LENGTH = 8;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Adresse e-mail (login)')
            ->addArgument('password', InputArgument::OPTIONAL, 'Mot de passe (sinon demandé en interactif)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = mb_strtolower(trim((string) $input->getArgument('email')));

        if ('' === $email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Adresse e-mail invalide.');

            return Command::FAILURE;
        }

        $password = (string) ($input->getArgument('password') ?? '');
        if ('' === $password) {
            if (!$input->isInteractive()) {
                $io->error('En non-interactif, passe le mot de passe en 2e argument.');

                return Command::FAILURE;
            }
            $password = (string) $io->askHidden('Mot de passe admin (min. '.self::MIN_LENGTH.' caractères)');
            $io->newLine();
            $confirm = (string) $io->askHidden('Confirmation');
            $io->newLine();
            if ($password !== $confirm) {
                $io->error('Les mots de passe ne correspondent pas.');

                return Command::FAILURE;
            }
        }

        if (strlen($password) < self::MIN_LENGTH) {
            $io->error('Mot de passe trop court (minimum '.self::MIN_LENGTH.' caractères).');

            return Command::FAILURE;
        }

        $repository = $this->entityManager->getRepository(User::class);
        $user = $repository->findOneBy(['email' => $email]);

        if (!$user instanceof User) {
            $user = (new User())->setEmail($email);
            $this->entityManager->persist($user);
            $created = true;
        } else {
            $created = false;
        }

        $user->setRoles(['ROLE_ADMIN']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->flush();

        $io->success($created
            ? 'Administrateur créé : '.$email
            : 'Compte existant promu administrateur et mot de passe mis à jour : '.$email);

        return Command::SUCCESS;
    }
}
