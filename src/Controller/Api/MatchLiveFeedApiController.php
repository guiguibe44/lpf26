<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\MatchLiveFeedService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/matchs')]
final class MatchLiveFeedApiController extends AbstractController
{
    #[Route('/live-feed', name: 'api_matchs_live_feed', methods: ['GET'])]
    public function liveFeed(MatchLiveFeedService $matchLiveFeedService): JsonResponse
    {
        return $this->json($matchLiveFeedService->buildFeed());
    }
}
