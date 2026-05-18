<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\UploadImageCategory;
use App\Service\ImageUploadOptimizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:images:optimize-uploads',
    description: 'Optimise les images déjà présentes dans public/uploads/ (redimensionnement + WebP).',
)]
final class OptimizeUploadedImagesCommand extends Command
{
    public function __construct(
        private readonly ImageUploadOptimizer $imageUploadOptimizer,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('subdir', null, InputOption::VALUE_OPTIONAL, 'Sous-dossier (avatars, jokers, …) ou tous si omis')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule sans modifier les fichiers');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $subdirFilter = $input->getOption('subdir');

        $categories = UploadImageCategory::cases();
        if (\is_string($subdirFilter) && '' !== $subdirFilter) {
            $category = UploadImageCategory::tryFromSubdir($subdirFilter);
            if (null === $category) {
                $io->error(sprintf('Sous-dossier inconnu : %s', $subdirFilter));

                return Command::FAILURE;
            }
            $categories = [$category];
        }

        $optimized = 0;
        $skipped = 0;

        foreach ($categories as $category) {
            $dir = $this->projectDir.'/public/uploads/'.$category->value;
            if (!is_dir($dir)) {
                continue;
            }

            $io->section($category->value);

            foreach (scandir($dir) ?: [] as $entry) {
                if ('.' === $entry || '..' === $entry) {
                    continue;
                }

                $path = $dir.'/'.$entry;
                if (!is_file($path)) {
                    continue;
                }

                if ($dryRun) {
                    $io->writeln('  [dry-run] '.$entry);
                    ++$optimized;
                    continue;
                }

                $before = filesize($path) ?: 0;
                $newBasename = $this->imageUploadOptimizer->optimizeAbsolutePath($path, $category);
                if (null === $newBasename) {
                    ++$skipped;
                    continue;
                }

                $afterPath = $dir.'/'.$newBasename;
                $after = is_file($afterPath) ? (filesize($afterPath) ?: 0) : $before;
                $io->writeln(sprintf(
                    '  %s → %s (%s → %s)',
                    $entry,
                    $newBasename,
                    $this->formatBytes($before),
                    $this->formatBytes($after),
                ));
                ++$optimized;
            }
        }

        $io->success(sprintf(
            '%s fichier(s) traité(s), %d ignoré(s).%s',
            $optimized,
            $skipped,
            $dryRun ? ' (simulation)' : '',
        ));

        return Command::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' o';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' Ko';
        }

        return round($bytes / 1048576, 2).' Mo';
    }
}
