<?php

namespace App\Controller\Admin;

use App\Entity\PushNotificationLog;
use App\Entity\User;
use App\Form\AdminManualPushType;
use App\Repository\PushNotificationLogRepository;
use App\Repository\PushSubscriptionRepository;
use App\Service\WebPushService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class PushNotificationAdminController extends AbstractController
{
    #[Route('/admin/push/send', name: 'admin_push_send', methods: ['GET', 'POST'])]
    public function send(
        Request $request,
        WebPushService $webPushService,
        PushSubscriptionRepository $pushSubscriptionRepository,
        PushNotificationLogRepository $pushNotificationLogRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $form = $this->createForm(AdminManualPushType::class);
        $form->handleRequest($request);

        $subscriptionCount = $pushSubscriptionRepository->countAll();
        $configured = $webPushService->isConfigured();

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$configured) {
                $this->addFlash('danger', 'Impossible d\'envoyer : configurez VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY et VAPID_SUBJECT dans .env.');

                return $this->redirectToRoute('admin_push_send');
            }

            if (0 === $subscriptionCount) {
                $this->addFlash('warning', 'Aucun appareil abonné : aucune notification envoyée.');

                return $this->redirectToRoute('admin_push_send');
            }

            $data = $form->getData();
            \assert(\is_array($data));

            try {
                $url = '' !== ($data['url'] ?? '') ? (string) $data['url'] : null;
                $result = $webPushService->sendBroadcast(
                    (string) $data['title'],
                    (string) $data['body'],
                    $url,
                );

                $log = (new PushNotificationLog())
                    ->setTitle((string) $data['title'])
                    ->setBody((string) $data['body'])
                    ->setUrl($url)
                    ->setTargetCount($subscriptionCount)
                    ->setSentCount($result['sent'])
                    ->setFailedCount($result['failed'])
                    ->setRemovedCount($result['removed']);

                $admin = $this->getUser();
                if ($admin instanceof User) {
                    $log->setSentBy($admin);
                }

                $entityManager->persist($log);
                $entityManager->flush();

                if (0 === $result['sent'] && $result['failed'] > 0) {
                    $this->addFlash('danger', sprintf(
                        'Aucune notification délivrée (%d échec(s)). Vérifiez var/log/dev.log et demandez aux joueurs de réactiver les notifications.',
                        $result['failed'],
                    ));
                } elseif (0 === $result['sent']) {
                    $this->addFlash('warning', 'Aucune notification délivrée (aucun abonnement actif côté FCM/Firefox).');
                } else {
                    $this->addFlash('success', sprintf(
                        'Campagne envoyée : %d notification(s) délivrée(s), %d échec(s), %d abonnement(s) expiré(s) supprimé(s).',
                        $result['sent'],
                        $result['failed'],
                        $result['removed'],
                    ));
                }
            } catch (\Throwable $e) {
                $this->addFlash('danger', 'Erreur lors de l\'envoi : '.$e->getMessage());
            }

            return $this->redirectToRoute('admin_push_send');
        }

        return $this->render('admin/push_send.html.twig', [
            'form' => $form->createView(),
            'subscription_count' => $subscriptionCount,
            'vapid_configured' => $configured,
            'push_history' => $pushNotificationLogRepository->findRecent(50),
        ]);
    }
}
