<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\GameMatchRepository;
use App\Service\PronosticScoringService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:match:rescore-all',
    description: 'Recalcule les points et les cotes de tous les matchs (après changement de mode cotes).',
)]
final class RescoreAllMatchesCommand extends Command
{
    public function __construct(
        private readonly GameMatchRepository $gameMatchRepository,
        private readonly PronosticScoringService $pronosticScoringService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $matches = $this->gameMatchRepository->findAll();
        $count = 0;

        foreach ($matches as $match) {
            $this->pronosticScoringService->rescoreForMatch($match);
            ++$count;
        }

        $io->success(sprintf('%d match(s) recalculé(s).', $count));

        return Command::SUCCESS;
    }
}
