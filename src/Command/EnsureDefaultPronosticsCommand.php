<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\DefaultPronosticService;
use App\Service\PronosticScoringService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:pronostics:ensure-defaults',
    description: 'Applique le pronostic par défaut 0-0 sur les matchs verrouillés pour tous les joueurs cotisés.',
)]
final class EnsureDefaultPronosticsCommand extends Command
{
    public function __construct(
        private readonly DefaultPronosticService $defaultPronosticService,
        private readonly PronosticScoringService $pronosticScoringService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $matches = $this->defaultPronosticService->ensureDefaultsForAllPayingPlayers();
        foreach ($matches as $match) {
            $this->pronosticScoringService->rescoreForMatch($match);
        }

        if ([] === $matches) {
            $io->success('Tous les pronostics par défaut (0-0) sont déjà en place.');
        } else {
            $io->success(sprintf(
                'Pronostics 0-0 appliqués et scores recalculés pour %d match(s).',
                count($matches),
            ));
        }

        return Command::SUCCESS;
    }
}
