<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\GameMatch;
use App\Entity\User;
use App\Repository\JokerRepository;
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

        try {
            $teamJokerService->placeJoker($user, $match, $joker);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'success' => true,
            'message' => sprintf('Joker « %s » posé sur ce match.', (string) $joker->getName()),
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
