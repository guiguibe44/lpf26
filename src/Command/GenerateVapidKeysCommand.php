<?php

namespace App\Command;

use Minishlink\WebPush\VAPID;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-vapid-keys',
    description: 'Génère une paire de clés VAPID pour les notifications Web Push (à copier dans .env).',
)]
final class GenerateVapidKeysCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $keys = VAPID::createVapidKeys();

        $io->title('Clés VAPID pour Web Push');
        $io->writeln('Ajoutez ces lignes à votre fichier <info>.env</info> :');
        $io->newLine();
        $io->writeln(sprintf('VAPID_PUBLIC_KEY=%s', $keys['publicKey']));
        $io->writeln(sprintf('VAPID_PRIVATE_KEY=%s', $keys['privateKey']));
        $io->writeln('VAPID_SUBJECT=mailto:contact@example.com');
        $io->newLine();
        $io->note('Remplacez VAPID_SUBJECT par une URL mailto ou https de contact (ex. mailto:admin@votredomaine.fr).');

        return Command::SUCCESS;
    }
}
