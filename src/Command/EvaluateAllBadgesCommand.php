<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\BadgeAwardRepository;
use App\Repository\GameMatchRepository;
use App\Repository\UserRepository;
use App\Service\Badge\BadgeEvaluator;
use App\Service\PronosticScoringService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:badges:evaluate-all',
    description: 'Recalcule les points puis attribue tous les badges automatiques.',
)]
final class EvaluateAllBadgesCommand extends Command
{
    public function __construct(
        private readonly GameMatchRepository $gameMatchRepository,
        private readonly BadgeEvaluator $badgeEvaluator,
        private readonly PronosticScoringService $pronosticScoringService,
        private readonly UserRepository $userRepository,
        private readonly BadgeAwardRepository $badgeAwardRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'skip-rescore',
            null,
            InputOption::VALUE_NONE,
            'Ne pas recalculer les points des pronos avant l’évaluation.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $matches = $this->gameMatchRepository->findMatchesFromDate(new \DateTimeImmutable('2000-01-01'));
        $played = array_values(array_filter(
            $matches,
            static fn ($m) => null !== $m->getScoreDomicile() && null !== $m->getScoreExterieur(),
        ));

        if (!$input->getOption('skip-rescore')) {
            $io->note(sprintf('Rescore de %d match(s) terminé(s)…', count($played)));
            foreach ($played as $match) {
                $this->pronosticScoringService->rescoreForMatch($match);
            }
        }

        $io->note(sprintf('Évaluation des badges sur %d match(s) terminé(s)…', count($played)));

        foreach ($played as $match) {
            $this->badgeEvaluator->evaluateAfterMatchRescore($match);
        }

        $users = $this->userRepository->findActivePlayersOrderedByEmail();
        foreach ($users as $user) {
            foreach ($played as $match) {
                $this->badgeEvaluator->evaluateOnPronosticSaved($user, $match);
            }
        }

        $totalAwards = $this->badgeAwardRepository->count([]);
        $io->success(sprintf('Évaluation terminée — %d attribution(s) en base.', $totalAwards));

        return Command::SUCCESS;
    }
}
