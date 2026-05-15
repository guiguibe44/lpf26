<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Enum\AdminRecipientScope;
use App\Form\AdminManualMessageType;
use App\Repository\PushNotificationLogRepository;
use App\Repository\PushSubscriptionRepository;
use App\Repository\UserRepository;
use App\Service\AdminManualMessageService;
use App\Service\WebPushService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class PushNotificationAdminController extends AbstractController
{
    #[Route('/admin/messages', name: 'admin_manual_messages', methods: ['GET', 'POST'])]
    public function send(
        Request $request,
        AdminManualMessageService $messageService,
        WebPushService $webPushService,
        PushSubscriptionRepository $pushSubscriptionRepository,
        UserRepository $userRepository,
        PushNotificationLogRepository $pushNotificationLogRepository,
    ): Response {
        $form = $this->createForm(AdminManualMessageType::class);
        $form->handleRequest($request);

        $vapidConfigured = $webPushService->isConfigured();
        $activePlayersCount = \count($userRepository->findActivePlayersOrderedByEmail());

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            \assert(\is_array($data));

            $sendPush = (bool) ($data['sendPush'] ?? false);
            $sendEmail = (bool) ($data['sendEmail'] ?? false);
            $scope = $data['recipientScope'] ?? AdminRecipientScope::All;
            \assert($scope instanceof AdminRecipientScope);

            if ($sendPush && !$vapidConfigured) {
                $this->addFlash('danger', 'Push impossible : configurez les clés VAPID dans .env.');

                return $this->redirectToRoute('admin_manual_messages');
            }

            $selected = $data['players'] ?? [];
            if ($selected instanceof \Doctrine\Common\Collections\Collection) {
                $selected = $selected->toArray();
            } elseif (!\is_array($selected)) {
                $selected = [];
            }
            $admin = $this->getUser();
            $sentBy = $admin instanceof User ? $admin : null;

            $url = '' !== ($data['url'] ?? '') ? (string) $data['url'] : null;

            try {
                $summary = $messageService->send(
                    $sendPush,
                    $sendEmail,
                    $scope,
                    $selected,
                    (string) $data['title'],
                    (string) $data['body'],
                    $url,
                    $sentBy,
                );

                $parts = [];
                if ($sendPush) {
                    $parts[] = sprintf('%d push', $summary['pushSent']);
                }
                if ($sendEmail) {
                    $parts[] = sprintf('%d e-mail(s)', $summary['emailsSent']);
                }
                $failParts = [];
                if ($summary['pushFailed'] > 0) {
                    $failParts[] = sprintf('%d push en échec', $summary['pushFailed']);
                }
                if ($summary['emailsFailed'] > 0) {
                    $failParts[] = sprintf('%d e-mail(s) en échec', $summary['emailsFailed']);
                }

                if ([] === $parts && [] === $failParts) {
                    $this->addFlash('warning', 'Aucun message envoyé (vérifiez les destinataires et les canaux).');
                } elseif ([] === $failParts) {
                    $this->addFlash('success', sprintf(
                        'Message envoyé à %d joueur(s) : %s.',
                        $summary['playersTargeted'],
                        implode(', ', $parts),
                    ));
                } else {
                    $this->addFlash('warning', sprintf(
                        'Envoi partiel (%s). %s.',
                        implode(', ', $parts) ?: 'aucun succès',
                        implode(', ', $failParts),
                    ));
                }
            } catch (\Throwable $e) {
                $this->addFlash('danger', 'Erreur lors de l\'envoi : '.$e->getMessage());
            }

            return $this->redirectToRoute('admin_manual_messages');
        }

        return $this->render('admin/manual_messages.html.twig', [
            'form' => $form->createView(),
            'subscription_count' => $pushSubscriptionRepository->countAll(),
            'active_players_count' => $activePlayersCount,
            'vapid_configured' => $vapidConfigured,
            'message_history' => $pushNotificationLogRepository->findRecent(50),
        ]);
    }
}
