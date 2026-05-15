<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Pronostic;
use App\Repository\GameMatchRepository;
use App\Repository\PronosticRepository;
use App\Repository\UserRepository;
use App\Service\PronosticScoringService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed:test-pronostics',
    description: 'Génère des pronostics de test pour les premiers matchs.',
)]
final class SeedTestPronosticsCommand extends Command
{
    public function __construct(
        private readonly GameMatchRepository $gameMatchRepository,
        private readonly UserRepository $userRepository,
        private readonly PronosticRepository $pronosticRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly PronosticScoringService $pronosticScoringService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $matches = $this->gameMatchRepository->findBy([], ['dateHeure' => 'ASC'], 3);
        if ([] === $matches) {
            $io->warning('Aucun match trouvé.');

            return Command::SUCCESS;
        }

        $users = $this->userRepository->findBy([], ['id' => 'ASC']);
        if ([] === $users) {
            $io->warning('Aucun utilisateur trouvé.');

            return Command::SUCCESS;
        }

        $created = 0;
        $updated = 0;
        $userIndex = 0;

        foreach ($matches as $match) {
            foreach ($users as $user) {
                $pronostic = $this->pronosticRepository->findOneBy([
                    'joueur' => $user,
                    'match' => $match,
                ]);

                $scoreDomicile = ($userIndex + (int) $match->getId()) % 4;
                $scoreExterieur = ($userIndex * 2 + (int) $match->getId()) % 4;

                if (!$pronostic instanceof Pronostic) {
                    $pronostic = new Pronostic();
                    $pronostic->setJoueur($user);
                    $pronostic->setMatch($match);
                    $this->entityManager->persist($pronostic);
                    $created++;
                } else {
                    $updated++;
                }

                $pronostic
                    ->setScoreDomicile($scoreDomicile)
                    ->setScoreExterieur($scoreExterieur);

                $userIndex++;
            }
        }

        $this->entityManager->flush();

        foreach ($matches as $match) {
            $this->pronosticScoringService->rescoreForMatch($match);
        }

        $io->success(sprintf(
            'Pronostics test générés sur %d matchs. Créés: %d, mis à jour: %d.',
            count($matches),
            $created,
            $updated
        ));

        return Command::SUCCESS;
    }
}
