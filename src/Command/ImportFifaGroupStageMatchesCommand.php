<?php

declare(strict_types=1);

namespace App\Command;

use App\Data\WorldCup2026Groups;
use App\Entity\GameMatch;
use App\Repository\CountryRepository;
use App\Repository\GameMatchRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import:fifa-group-matches',
    description: 'Importe les affiches de groupes FIFA (dates provisoires).',
)]
final class ImportFifaGroupStageMatchesCommand extends Command
{
    public function __construct(
        private readonly CountryRepository $countryRepository,
        private readonly GameMatchRepository $gameMatchRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $baseDate = new \DateTimeImmutable('2026-06-11 12:00:00');
        $created = 0;
        $skipped = 0;
        $slot = 0;

        foreach (WorldCup2026Groups::TEAMS_BY_LETTER as $group => $teams) {
            $fixtures = $this->buildRoundRobinFixtures($teams);

            foreach ($fixtures as [$homeName, $awayName]) {
                $home = $this->countryRepository->findOneBy(['nom' => $homeName]);
                $away = $this->countryRepository->findOneBy(['nom' => $awayName]);

                if (null === $home || null === $away) {
                    $skipped++;
                    continue;
                }

                $dateHeure = $baseDate->modify(sprintf('+%d hours', $slot * 2));
                $slot++;

                $existing = $this->gameMatchRepository->findOneBy([
                    'paysDomicile' => $home,
                    'paysExterieur' => $away,
                    'dateHeure' => $dateHeure,
                ]);

                if (null !== $existing) {
                    $skipped++;
                    continue;
                }

                $match = new GameMatch();
                $match
                    ->setPaysDomicile($home)
                    ->setPaysExterieur($away)
                    ->setDateHeure($dateHeure)
                    ->setPhase('Group '.$group)
                    ->setStatut('SCHEDULED');

                $this->entityManager->persist($match);
                $created++;
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            'Import terminé. Matchs créés: %d, ignorés: %d. Dates provisoires appliquées.',
            $created,
            $skipped
        ));
        $io->warning('Les dates/heures viennent d’une grille provisoire. À remplacer dès qu’une source FIFA exploitable est disponible.');

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $teams
     * @return list<array{0:string,1:string}>
     */
    private function buildRoundRobinFixtures(array $teams): array
    {
        return [
            [$teams[0], $teams[1]],
            [$teams[2], $teams[3]],
            [$teams[0], $teams[2]],
            [$teams[1], $teams[3]],
            [$teams[0], $teams[3]],
            [$teams[1], $teams[2]],
        ];
    }
}
