<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Wc2026SyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync:buteur-photos',
    description: 'Télécharge les photos des buteurs (URL distantes) vers public/uploads/buteurs/.',
)]
final class SyncButeurPhotosCommand extends Command
{
    public function __construct(
        private readonly Wc2026SyncService $wc2026SyncService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $result = $this->wc2026SyncService->downloadAllButeurPhotos();

        $io->success(sprintf(
            'Photos joueurs : %d téléchargées, %d ignorées, %d échecs.',
            $result['downloaded'],
            $result['skipped'],
            $result['failed'],
        ));

        return Command::SUCCESS;
    }
}
