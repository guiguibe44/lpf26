<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\JokerTestScenarioStepRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:joker-test:advance',
    description: 'Avance d’une étape le scénario jokers (debug CLI).',
)]
final class JokerTestScenarioAdvanceCommand extends Command
{
    public function __construct(
        private readonly JokerTestScenarioStepRunner $stepRunner,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $result = $this->stepRunner->advance();
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success((string) ($result['step']['label'] ?? 'Étape'));
        if (isset($result['result'])) {
            $io->writeln(json_encode($result['result'], \JSON_PRETTY_PRINT));
        }

        return Command::SUCCESS;
    }
}
