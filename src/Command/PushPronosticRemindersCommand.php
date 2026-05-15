<?php

namespace App\Command;

use App\Service\MatchPronosticReminderService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:push:pronostic-reminders',
    description: 'Relances automatiques pronostic (push si abonné, sinon e-mail) — cron toutes les 5–10 min.',
)]
final class PushPronosticRemindersCommand extends Command
{
    public function __construct(
        private readonly MatchPronosticReminderService $reminderService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule sans envoyer ni marquer les matchs.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $summary = $this->reminderService->processDueReminders(dryRun: $dryRun);

        if ($dryRun) {
            $io->note('Mode simulation (aucun envoi, aucune écriture en base).');
        }

        $io->table(
            ['Indicateur', 'Valeur'],
            [
                ['Matchs examinés', (string) $summary['matchesChecked']],
                ['Matchs en relance', (string) $summary['matchesReminded']],
                ['Joueurs ciblés', (string) $summary['playersTargeted']],
                ['Push délivrés', (string) $summary['pushSent']],
                ['Push en échec', (string) $summary['pushFailed']],
                ['E-mails envoyés', (string) $summary['emailsSent']],
                ['E-mails en échec', (string) $summary['emailsFailed']],
            ],
        );

        if ($summary['matchesReminded'] > 0 && !$dryRun) {
            $io->success('Relances traitées.');
        } elseif (0 === $summary['matchesReminded']) {
            $io->writeln('Aucune relance due pour le moment.');
        }

        return Command::SUCCESS;
    }
}
