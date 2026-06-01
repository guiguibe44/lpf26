<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\JokerTestScenarioResetService;
use App\Service\JokerTestScenarioSeedService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:joker-test:setup',
    description: 'Réinitialise équipes/joueurs (hors admin) et prépare le scénario de test jokers (local).',
)]
final class JokerTestScenarioSetupCommand extends Command
{
    public function __construct(
        private readonly JokerTestScenarioResetService $resetService,
        private readonly JokerTestScenarioSeedService $seedService,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force-prod', null, InputOption::VALUE_NONE, 'Autoriser l’exécution hors environnement dev (dangereux).');
        $this->addOption('yes', 'y', InputOption::VALUE_NONE, 'Ne pas demander de confirmation.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ('dev' !== $this->environment && !$input->getOption('force-prod')) {
            $io->error('Cette commande est réservée à l’environnement dev. Utilisez --force-prod si vous savez ce que vous faites.');

            return Command::FAILURE;
        }

        if (!$input->getOption('yes') && !$io->confirm('Supprimer toutes les équipes et tous les joueurs sauf les administrateurs, puis recréer le scénario jokers ?', false)) {
            $io->note('Annulé.');

            return Command::SUCCESS;
        }

        $io->section('Nettoyage');
        $reset = $this->resetService->reset();
        $io->listing([
            sprintf('Équipes supprimées : %d', $reset['teams']),
            sprintf('Joueurs supprimés : %d', $reset['users']),
            sprintf('Matchs test supprimés : %d', $reset['matches']),
            sprintf('Pronostics supprimés : %d', $reset['pronostics']),
        ]);

        $io->section('Création du jeu de données');
        try {
            $seed = $this->seedService->seed();
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success('Scénario jokers prêt.');
        $teamRows = [];
        foreach ($seed['teams'] as $key => $id) {
            $teamRows[] = [$key, (string) $id];
        }
        $io->table(['Équipe', 'ID'], $teamRows);
        $matchIdStrings = array_map(static fn (int $id): string => (string) $id, $seed['match_ids']);
        $io->writeln('Matchs : '.implode(', ', $matchIdStrings));
        $io->note('Page de suivi : /admin/scenario-jokers');
        $io->note('Mot de passe des comptes test : Test1234!');

        return Command::SUCCESS;
    }
}
