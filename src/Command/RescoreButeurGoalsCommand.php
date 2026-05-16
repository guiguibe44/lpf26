<?php

namespace App\Command;

use App\Repository\GameMatchRepository;
use App\Service\ButeurGoalScoringService;
use App\Service\TeamRankingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:rescore:buteur-goals',
    description: 'Recalcule les points et cotes des buts buteur, puis met à jour le classement.',
)]
final class RescoreButeurGoalsCommand extends Command
{
    public function __construct(
        private readonly ButeurGoalScoringService $buteurGoalScoringService,
        private readonly GameMatchRepository $gameMatchRepository,
        private readonly TeamRankingService $teamRankingService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->buteurGoalScoringService->rescoreAll();
        $io->success('Points buteur recalculés pour tous les buts enregistrés.');

        $latestMatch = $this->gameMatchRepository->findLatestFinishedMatch();
        if (null !== $latestMatch) {
            $this->teamRankingService->rebuildSnapshotsFromMatch($latestMatch);
            $io->success('Classement équipes reconstruit.');
        } else {
            $io->note('Aucun match terminé : classement non recalculé.');
        }

        return Command::SUCCESS;
    }
}
