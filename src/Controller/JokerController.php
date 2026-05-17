<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\GameMatch;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\JokerRepository;
use App\Repository\TeamRepository;
use App\Service\TeamJokerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class JokerController extends AbstractController
{
    #[Route('/matchs/{id}/jokers', name: 'app_match_jokers_state', methods: ['GET'])]
    public function matchState(GameMatch $match, TeamJokerService $teamJokerService): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json($teamJokerService->buildMatchPickerState($user, $match));
    }

    #[Route('/matchs/{id}/joker', name: 'app_match_joker_place', methods: ['POST'])]
    public function place(
        Request $request,
        GameMatch $match,
        TeamJokerService $teamJokerService,
        JokerRepository $jokerRepository,
        TeamRepository $teamRepository,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        $jokerId = $request->request->getInt('joker_id');
        if ($jokerId <= 0) {
            return new JsonResponse(['error' => 'Joker invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $joker = $jokerRepository->find($jokerId);
        if (null === $joker) {
            return new JsonResponse(['error' => 'Joker introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $targetTeam = null;
        $targetTeamId = $request->request->getInt('target_team_id');
        if ($targetTeamId > 0) {
            $targetTeam = $teamRepository->find($targetTeamId);
            if (null === $targetTeam) {
                return new JsonResponse(['error' => 'Équipe cible introuvable.'], Response::HTTP_BAD_REQUEST);
            }
        }

        try {
            $teamJokerService->placeJoker($user, $match, $joker, $targetTeam);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $message = $teamJokerService->buildPlacementSuccessMessage($joker, $targetTeam, $match);

        return $this->json([
            'success' => true,
            'message' => $message,
            'state' => $teamJokerService->buildMatchPickerState($user, $match),
        ]);
    }

    #[Route('/matchs/{id}/joker/remove', name: 'app_match_joker_remove', methods: ['POST'])]
    public function remove(GameMatch $match, TeamJokerService $teamJokerService): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $teamJokerService->removeJokerFromMatch($user, $match);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'success' => true,
            'message' => 'Joker retiré de ce match.',
            'state' => $teamJokerService->buildMatchPickerState($user, $match),
        ]);
    }
}
