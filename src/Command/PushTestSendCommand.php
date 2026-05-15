<?php

namespace App\Command;

use App\Repository\PushSubscriptionRepository;
use App\Service\WebPushService;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:push-test-send',
    description: 'Envoie une notification test et affiche le détail par abonnement (debug local).',
)]
final class PushTestSendCommand extends Command
{
    public function __construct(
        private readonly WebPushService $webPushService,
        private readonly PushSubscriptionRepository $pushSubscriptionRepository,
        private readonly string $vapidPublicKey,
        private readonly string $vapidPrivateKey,
        private readonly string $vapidSubject,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->webPushService->isConfigured()) {
            $io->error('VAPID non configuré.');

            return Command::FAILURE;
        }

        $subscriptions = $this->pushSubscriptionRepository->findAllForBroadcast();
        if ([] === $subscriptions) {
            $io->warning('Aucun abonnement en base.');

            return Command::SUCCESS;
        }

        $payload = json_encode([
            'title' => 'Test LPF\'26',
            'body' => 'Notification de test ('.date('H:i:s').')',
            'url' => '/accueil',
        ], \JSON_THROW_ON_ERROR);

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => $this->vapidSubject,
                'publicKey' => $this->vapidPublicKey,
                'privateKey' => $this->vapidPrivateKey,
            ],
        ]);

        foreach ($subscriptions as $entity) {
            $encoding = $entity->getContentEncoding() ?? 'aes128gcm';
            $io->section(sprintf('Abonnement #%d (encoding: %s)', $entity->getId(), $encoding));
            $io->writeln(substr($entity->getEndpoint(), 0, 80).'…');

            foreach (['aes128gcm', 'aesgcm'] as $tryEncoding) {
                $sub = Subscription::create([
                    'endpoint' => $entity->getEndpoint(),
                    'keys' => [
                        'p256dh' => $entity->getPublicKey(),
                        'auth' => $entity->getAuthToken(),
                    ],
                    'contentEncoding' => $tryEncoding,
                ]);
                $webPush->queueNotification($sub, $payload);
                foreach ($webPush->flush() as $report) {
                    $status = $report->isSuccess() ? '<info>OK</info>' : '<error>FAIL</error>';
                    $io->writeln(sprintf('  [%s] %s — %s', $tryEncoding, $status, $report->getReason()));
                    if ($report->getResponse()) {
                        $io->writeln('  HTTP '.$report->getResponse()->getStatusCode());
                    }
                }
            }
        }

        return Command::SUCCESS;
    }
}
