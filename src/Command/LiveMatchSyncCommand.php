<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\LiveMatchSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:live-match:sync',
    description: 'Synchro API-Football des matchs en cours (scores, buts, finalisation) — à planifier toutes les 3 min en cron.',
)]
final class LiveMatchSyncCommand extends Command
{
    public function __construct(
        private readonly LiveMatchSyncService $liveMatchSyncService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $summary = $this->liveMatchSyncService->syncActiveMatches();

        $io->table(
            ['Indicateur', 'Valeur'],
            [
                ['Matchs examinés', (string) $summary['matches_checked']],
                ['Matchs synchronisés', (string) $summary['matches_synced']],
                ['Buts créés', (string) $summary['goals_created']],
                ['Matchs finalisés', (string) $summary['matches_finalized']],
                ['Appels API', (string) $summary['api_calls']],
            ],
        );

        foreach ($summary['errors'] as $error) {
            $io->warning($error);
        }

        if ([] === $summary['errors']) {
            $io->success('Synchro live terminée.');
        } else {
            $io->note('Synchro terminée avec des erreurs partielles.');
        }

        return Command::SUCCESS;
    }
}
