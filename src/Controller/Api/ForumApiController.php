<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\TeamMemberRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED')]
#[Route('/api/forum')]
final class ForumApiController extends AbstractController
{
    #[Route('/mentions', name: 'api_forum_mention_suggestions', methods: ['GET'])]
    public function mentionSuggestions(Request $request, TeamMemberRepository $teamMemberRepository): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Non authentifié.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $query = trim((string) $request->query->get('q', ''));

        $suggestions = $teamMemberRepository->searchPlayersForForumMention(
            $query,
            $user->getId(),
            12,
        );

        return $this->json(['suggestions' => $suggestions]);
    }
}
