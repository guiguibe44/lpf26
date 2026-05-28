<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Wc2026SyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:wc2026:sync',
    description: 'Synchronise pays, matchs, joueurs et buts via API-Sports Football v3 (API-Football).',
)]
final class Wc2026SyncCommand extends Command
{
    public function __construct(
        private readonly Wc2026SyncService $syncService,
        private readonly int $defaultPlayersSyncMaxRequests,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('countries-limit', null, InputOption::VALUE_REQUIRED, 'Nombre max de pays à récupérer', '500')
            ->addOption('players-limit', null, InputOption::VALUE_REQUIRED, 'Plafond requêtes HTTP API-Sports pour la synchro joueurs (équipes + pages)', (string) $this->defaultPlayersSyncMaxRequests)
            ->addOption('matches-limit', null, InputOption::VALUE_REQUIRED, 'Nombre max de matchs à récupérer', '500')
            ->addOption('players-per-team', null, InputOption::VALUE_REQUIRED, 'Plafond optionnel par sélection nationale (0 = effectif complet API)', '0')
            ->addOption('only', null, InputOption::VALUE_REQUIRED, 'countries|matches|players|goals');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $countriesLimit = (int) $input->getOption('countries-limit');
        $playersLimit = (int) $input->getOption('players-limit');
        $matchesLimit = (int) $input->getOption('matches-limit');
        $only = (string) ($input->getOption('only') ?? '');
        $playersPerTeamRaw = (int) $input->getOption('players-per-team');
        $maxPlayersPerTeam = $playersPerTeamRaw > 0 ? $playersPerTeamRaw : null;

        try {
            if ('' === $only || 'countries' === $only) {
                $resultCountries = $this->syncService->syncCountries($countriesLimit);
                $io->success(sprintf(
                    'Pays synchronisés - créés: %d, mis à jour: %d, drapeaux téléchargés: %d',
                    $resultCountries['created'],
                    $resultCountries['updated'],
                    $resultCountries['flags_downloaded'],
                ));
            }

            if ('' === $only || 'matches' === $only) {
                $resultMatches = $this->syncService->syncMatches($matchesLimit);
                $io->success(sprintf(
                    'Matchs synchronisés - créés: %d, mis à jour: %d, ignorés: %d',
                    $resultMatches['created'],
                    $resultMatches['updated'],
                    $resultMatches['skipped']
                ));
            }

            if ('' === $only || 'players' === $only) {
                $resultPlayers = $this->syncService->syncButeurs($playersLimit, $maxPlayersPerTeam);
                $capMsg = null !== $maxPlayersPerTeam ? sprintf(' (plafond %d joueurs / sélection)', $maxPlayersPerTeam) : ' (sélections compétition)';
                $io->success(sprintf(
                    'Joueurs synchronisés%s - créés: %d, mis à jour: %d, ignorés: %d',
                    $capMsg,
                    $resultPlayers['created'],
                    $resultPlayers['updated'],
                    $resultPlayers['skipped']
                ));
                if (!empty($resultPlayers['cancelled'])) {
                    $io->warning('Synchro joueurs interrompue (demande d arret via admin ou fichier stop).');
                }
            }

            if ('' === $only || 'goals' === $only) {
                $resultGoals = $this->syncService->syncButsFromFixtureEvents();
                $io->success(sprintf(
                    'Buts synchronisés - créés: %d, lignes événements ignorées: %d, appels API: %d',
                    $resultGoals['created'],
                    $resultGoals['skipped'],
                    $resultGoals['api_calls']
                ));
            }
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
