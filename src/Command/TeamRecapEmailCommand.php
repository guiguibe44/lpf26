<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\TeamRecapEmailService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:email:team-recap',
    description: 'Récap d’équipe par e-mail (tous les 2 jours vers 9 h 30, Europe/Paris).',
)]
final class TeamRecapEmailCommand extends Command
{
    public function __construct(
        private readonly TeamRecapEmailService $recapEmailService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule sans envoyer ni journaliser.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Ignore l’intervalle de 2 jours et la fenêtre horaire.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $force = (bool) $input->getOption('force');

        $summary = $this->recapEmailService->process(dryRun: $dryRun, force: $force);

        if ($dryRun) {
            $io->note('Mode simulation (aucun envoi, aucune écriture en base).');
        }

        $rows = [
            ['Ignoré', $summary['skipped'] ? 'oui' : 'non'],
            ['Raison', (string) ($summary['reason'] ?? '—')],
            ['Début période', (string) ($summary['period_start'] ?? '—')],
            ['Fin période', (string) ($summary['period_end'] ?? '—')],
            ['Matchs dans la période', (string) $summary['matches_in_period']],
            ['Équipes notifiées', (string) $summary['teams_notified']],
            ['E-mails envoyés', (string) $summary['emails_sent']],
            ['E-mails en échec', (string) $summary['emails_failed']],
        ];

        $io->table(['Indicateur', 'Valeur'], $rows);

        if (!$summary['skipped'] && $summary['emails_sent'] > 0 && !$dryRun) {
            $io->success('Récap d’équipe envoyé.');
        } elseif ($summary['skipped']) {
            $io->writeln((string) ($summary['reason'] ?? 'Rien à envoyer.'));
        }

        return Command::SUCCESS;
    }
}
