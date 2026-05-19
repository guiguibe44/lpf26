<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\GameMatchRepository;
use App\Service\TestMatchScenarioRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-match:step',
    description: 'Étape d’un scénario de match test manuel (relance prono, CO, but, fin).',
)]
final class TestMatchScenarioCommand extends Command
{
    public function __construct(
        private readonly GameMatchRepository $gameMatchRepository,
        private readonly TestMatchScenarioRunner $testMatchScenarioRunner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('match-id', null, InputOption::VALUE_REQUIRED, 'ID du match test')
            ->addOption('step', null, InputOption::VALUE_REQUIRED, 'reminder|kickoff|goal|finish|reset-reminder|info')
            ->addOption('buteur-id', null, InputOption::VALUE_REQUIRED, 'ID buteur (étape goal)')
            ->addOption('minute', null, InputOption::VALUE_REQUIRED, 'Minute du but (étape goal)', '0')
            ->addOption('score-home', null, InputOption::VALUE_REQUIRED, 'Score domicile final (étape finish, optionnel)')
            ->addOption('score-away', null, InputOption::VALUE_REQUIRED, 'Score extérieur final (étape finish, optionnel)')
            ->addOption('now', null, InputOption::VALUE_REQUIRED, 'Date/heure simulée pour reminder (ex. 2026-05-21 14:00:00)')
            ->addOption('no-notify', null, InputOption::VALUE_NONE, 'Pas de notification push buteur (étape goal)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulation sans envoi (étape reminder)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $matchId = $input->getOption('match-id');
        $step = $input->getOption('step');
        if (!\is_string($matchId) || !ctype_digit($matchId)) {
            $io->error('Option --match-id requise (entier).');

            return Command::FAILURE;
        }
        if (!\is_string($step) || '' === $step) {
            $io->error('Option --step requise : reminder, kickoff, goal, finish, reset-reminder, info.');

            return Command::FAILURE;
        }

        $match = $this->gameMatchRepository->find((int) $matchId);
        if (null === $match) {
            $io->error(sprintf('Match #%s introuvable.', $matchId));

            return Command::FAILURE;
        }

        try {
            $result = $this->testMatchScenarioRunner->run($this->buildParams($input, (int) $matchId, $step));
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if ('info' === $step) {
            $io->title(sprintf('Match #%d — %s', $result['match_id'], $result['label']));
            $io->definitionList(
                ['Coup d\'envoi', $result['kickoff'] ?? '—'],
                ['Relance due à partir de', $result['reminder_due_from'] ?? '—'],
                ['Relance envoyée le', $result['push_reminder_sent_at'] ?? 'non'],
                ['Statut', $result['statut']],
                ['Score', sprintf('%s - %s', $result['score']['home'] ?? '—', $result['score']['away'] ?? '—')],
                ['Minute live', (string) ($result['live_minute'] ?? '—')],
                ['Finalisé le', $result['finalized_at'] ?? 'non'],
            );
        } else {
            $io->success('Étape « '.$step.' » exécutée.');
            $io->writeln(json_encode($result, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
        }

        if ('reminder' === $step) {
            $io->note('Seuls les joueurs cotisés sans aucune ligne Pronostic sur ce match sont relancés.');
        }

        return Command::SUCCESS;
    }

    /**
     * @return array{
     *     match_id: int,
     *     step: string,
     *     buteur_id?: int|null,
     *     minute?: int,
     *     score_home?: int|null,
     *     score_away?: int|null,
     *     now?: string|null,
     *     dry_run?: bool,
     *     notify?: bool,
     * }
     */
    private function buildParams(InputInterface $input, int $matchId, string $step): array
    {
        $params = [
            'match_id' => $matchId,
            'step' => $step,
            'dry_run' => (bool) $input->getOption('dry-run'),
            'notify' => !$input->getOption('no-notify'),
        ];

        $now = $input->getOption('now');
        if (\is_string($now) && '' !== $now) {
            $params['now'] = $now;
        }

        $buteurId = $input->getOption('buteur-id');
        if (\is_string($buteurId) && ctype_digit($buteurId)) {
            $params['buteur_id'] = (int) $buteurId;
        }

        $params['minute'] = (int) $input->getOption('minute');

        $scoreHome = $input->getOption('score-home');
        $scoreAway = $input->getOption('score-away');
        if (\is_string($scoreHome) && ctype_digit($scoreHome)) {
            $params['score_home'] = (int) $scoreHome;
        }
        if (\is_string($scoreAway) && ctype_digit($scoreAway)) {
            $params['score_away'] = (int) $scoreAway;
        }

        return $params;
    }
}
