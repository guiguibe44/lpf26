<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ApiFootballClient;
use App\Service\Wc2026SyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync:country-flags',
    description: 'Télécharge les drapeaux des pays (URL distantes ou API-Sports) vers public/uploads/drapeaux/.',
)]
final class SyncCountryFlagsCommand extends Command
{
    public function __construct(
        private readonly Wc2026SyncService $wc2026SyncService,
        private readonly ApiFootballClient $apiFootballClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('from-api', null, InputOption::VALUE_NONE, 'Synchronise d’abord les pays via API-Sports puis télécharge les drapeaux');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $fromApi = (bool) $input->getOption('from-api');

        if ($fromApi) {
            if (!$this->apiFootballClient->isConfigured()) {
                $io->error('API_FOOTBALL_KEY manquante : impossible de synchroniser depuis l’API.');

                return Command::FAILURE;
            }

            $result = $this->wc2026SyncService->syncCountries(500);
            $io->success(sprintf(
                'Pays API : %d créés, %d mis à jour, %d drapeaux téléchargés.',
                $result['created'],
                $result['updated'],
                $result['flags_downloaded'],
            ));

            return Command::SUCCESS;
        }

        $result = $this->wc2026SyncService->downloadAllCountryFlags();

        $io->success(sprintf(
            'Drapeaux : %d téléchargés, %d ignorés, %d échecs.',
            $result['downloaded'],
            $result['skipped'],
            $result['failed'],
        ));

        return Command::SUCCESS;
    }
}
