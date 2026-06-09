<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\BadgeAwardRepository;
use App\Repository\TeamMemberRepository;
use App\Service\Badge\BadgeCollectionBuilder;
use App\Service\BadgeFeature;
use App\Service\Badge\BadgeUnlockSimulator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED')]
#[Route('/api/badges')]
final class BadgeApiController extends AbstractController
{
    #[Route('/unseen', name: 'api_badges_unseen', methods: ['GET'])]
    public function unseen(
        BadgeCollectionBuilder $badgeCollectionBuilder,
        BadgeFeature $badgeFeature,
        TeamMemberRepository $teamMemberRepository,
    ): JsonResponse {
        $user = $this->requireUser();
        if (!$badgeFeature->isActiveForUser($user)) {
            return $this->json(['badges' => [], 'accountUrl' => $this->accountBadgesUrl()]);
        }

        $team = $teamMemberRepository->findOneBy(['player' => $user])?->getTeam();

        return $this->json([
            'badges' => $badgeCollectionBuilder->buildUnseenNotifications($user, $team),
            'accountUrl' => $this->accountBadgesUrl(),
        ]);
    }

    #[Route('/mark-seen', name: 'api_badges_mark_seen', methods: ['POST'])]
    public function markSeen(
        Request $request,
        BadgeFeature $badgeFeature,
        BadgeAwardRepository $badgeAwardRepository,
        TeamMemberRepository $teamMemberRepository,
    ): JsonResponse {
        $user = $this->requireUser();
        if (!$badgeFeature->isActiveForUser($user)) {
            return $this->json(['updated' => 0]);
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->json(['error' => 'JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $ids = [];
        foreach ($data['ids'] ?? [] as $rawId) {
            if (is_numeric($rawId)) {
                $ids[] = (int) $rawId;
            }
        }

        $team = $teamMemberRepository->findOneBy(['player' => $user])?->getTeam();
        $updated = $badgeAwardRepository->markSeenForUserAndTeam($ids, $user, $team);

        return $this->json(['updated' => $updated]);
    }

    #[Route('/simulate-unlock', name: 'api_badges_simulate_unlock', methods: ['POST'])]
    public function simulateUnlock(
        KernelInterface $kernel,
        BadgeFeature $badgeFeature,
        BadgeUnlockSimulator $badgeUnlockSimulator,
        TeamMemberRepository $teamMemberRepository,
    ): JsonResponse {
        if ('dev' !== $kernel->getEnvironment()) {
            throw new NotFoundHttpException();
        }

        if (!$badgeFeature->isEnabled()) {
            return $this->json(
                ['error' => 'BADGES_ENABLED=false — activez les badges en local (.env.local).'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $user = $this->requireUser();
        $team = $teamMemberRepository->findOneBy(['player' => $user])?->getTeam();

        try {
            $badge = $badgeUnlockSimulator->prepareUnseenNotification($user, $team);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'badge' => $badge,
            'accountUrl' => $this->accountBadgesUrl(),
        ]);
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function accountBadgesUrl(): string
    {
        return $this->generateUrl('app_account').'#tab-badges';
    }
}
