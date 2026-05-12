<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Repository\TeamMemberRepository;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed:test-teams',
    description: 'Crée 4 équipes de test avec 2 joueurs par équipe.',
)]
final class SeedTestTeamsCommand extends Command
{
    /**
     * @var array<int, array{name:string, slogan:string, players:array<int, array{email:string,nickname:string}>}>
     */
    private const DATA = [
        [
            'name' => 'Lions Test',
            'slogan' => 'On ne lâche rien',
            'players' => [
                ['email' => 'test1a@lpf26.local', 'nickname' => 'lion_alpha'],
                ['email' => 'test1b@lpf26.local', 'nickname' => 'lion_beta'],
            ],
        ],
        [
            'name' => 'Aigles Test',
            'slogan' => 'Toujours plus haut',
            'players' => [
                ['email' => 'test2a@lpf26.local', 'nickname' => 'aigle_alpha'],
                ['email' => 'test2b@lpf26.local', 'nickname' => 'aigle_beta'],
            ],
        ],
        [
            'name' => 'Panthères Test',
            'slogan' => 'Rapides et précis',
            'players' => [
                ['email' => 'test3a@lpf26.local', 'nickname' => 'panthere_alpha'],
                ['email' => 'test3b@lpf26.local', 'nickname' => 'panthere_beta'],
            ],
        ],
        [
            'name' => 'Requins Test',
            'slogan' => 'Froids dans le money time',
            'players' => [
                ['email' => 'test4a@lpf26.local', 'nickname' => 'requin_alpha'],
                ['email' => 'test4b@lpf26.local', 'nickname' => 'requin_beta'],
            ],
        ],
    ];

    private const DEFAULT_PASSWORD = 'Test1234!';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TeamRepository $teamRepository,
        private readonly UserRepository $userRepository,
        private readonly TeamMemberRepository $teamMemberRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $teamsCreated = 0;
        $usersCreated = 0;
        $membersCreated = 0;

        foreach (self::DATA as $teamData) {
            $team = $this->teamRepository->findOneBy(['name' => $teamData['name']]);
            if (!$team instanceof Team) {
                $team = new Team();
                $team->setName($teamData['name']);
                $this->entityManager->persist($team);
                $teamsCreated++;
            }

            $team->setSlogan($teamData['slogan']);

            foreach ($teamData['players'] as $playerData) {
                $user = $this->userRepository->findOneBy(['email' => $playerData['email']]);
                if (!$user instanceof User) {
                    $user = new User();
                    $user->setEmail($playerData['email']);
                    $user->setPassword($this->passwordHasher->hashPassword($user, self::DEFAULT_PASSWORD));
                    $user->setRoles(['ROLE_USER']);
                    $user->setCotisationPayee(true);
                    $this->entityManager->persist($user);
                    $usersCreated++;
                }

                $existingMemberByPlayer = $this->teamMemberRepository->findOneBy(['player' => $user]);
                if ($existingMemberByPlayer instanceof TeamMember) {
                    $existingMemberByPlayer->setTeam($team);
                    $existingMemberByPlayer->setNickname($playerData['nickname']);
                    continue;
                }

                $member = new TeamMember();
                $member->setTeam($team);
                $member->setPlayer($user);
                $member->setNickname($playerData['nickname']);
                $this->entityManager->persist($member);
                $membersCreated++;
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            'Seed terminé. Équipes créées: %d, utilisateurs créés: %d, membres créés: %d.',
            $teamsCreated,
            $usersCreated,
            $membersCreated
        ));
        $io->note(sprintf('Mot de passe test pour les nouveaux comptes: %s', self::DEFAULT_PASSWORD));

        return Command::SUCCESS;
    }
}
