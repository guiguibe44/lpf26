<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\GameMatch;
use App\Entity\Pronostic;
use App\Entity\User;
use App\Repository\GameMatchRepository;
use App\Repository\PronosticRepository;
use App\Service\DefaultPronosticService;
use App\Service\MatchStatusResolver;
use App\Service\PronosticScoringService;
use App\Service\Badge\BadgeEvaluator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PronosticController extends AbstractController
{
    #[Route('/pronostics', name: 'app_pronostics', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        GameMatchRepository $gameMatchRepository,
        PronosticRepository $pronosticRepository,
        EntityManagerInterface $entityManager,
        PronosticScoringService $pronosticScoringService,
        DefaultPronosticService $defaultPronosticService,
        BadgeEvaluator $badgeEvaluator,
        MatchStatusResolver $matchStatusResolver,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($request->isMethod('POST')) {
            $result = $this->handleSubmit(
                $request,
                $user,
                $gameMatchRepository,
                $pronosticRepository,
                $entityManager,
                $pronosticScoringService,
                $badgeEvaluator,
                $matchStatusResolver,
            );

            if ($this->wantsJson($request)) {
                return $this->jsonSaveResult($result);
            }

            $this->applyFlashFromResult($result);

            return $this->redirectToRoute('app_pronostics');
        }

        $matches = $gameMatchRepository->findBy([], ['dateHeure' => 'ASC']);
        $defaultPronosticService->ensureDefaultsForUser($user, $matches);
        $pronostics = $pronosticRepository->findBy(['joueur' => $user]);
        $pronosticsByMatchId = [];

        foreach ($pronostics as $pronostic) {
            $match = $pronostic->getMatch();
            if (null !== $match?->getId()) {
                $pronosticsByMatchId[$match->getId()] = $pronostic;
            }
        }

        return $this->render('pronostic/index.html.twig', [
            'matches' => $matches,
            'pronostics_by_match_id' => $pronosticsByMatchId,
            'now' => new \DateTimeImmutable(),
            'prono_access_blocked' => !$user->isCotisationPayee(),
        ]);
    }

    #[Route('/matchs/{id}/pronostic', name: 'app_match_pronostic_save', methods: ['POST'])]
    public function saveForMatch(
        Request $request,
        GameMatch $match,
        PronosticRepository $pronosticRepository,
        EntityManagerInterface $entityManager,
        PronosticScoringService $pronosticScoringService,
        BadgeEvaluator $badgeEvaluator,
        MatchStatusResolver $matchStatusResolver,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            if ($this->wantsJson($request)) {
                return new JsonResponse(['ok' => false, 'message' => 'Non authentifié.'], Response::HTTP_UNAUTHORIZED);
            }

            return $this->redirectToRoute('app_login');
        }

        $result = $this->savePronostic(
            $request,
            $user,
            $match,
            $pronosticRepository,
            $entityManager,
            $pronosticScoringService,
            $badgeEvaluator,
            $matchStatusResolver,
        );

        if ($this->wantsJson($request)) {
            return $this->jsonSaveResult($result);
        }

        $this->applyFlashFromResult($result);

        return $this->redirectToRoute('app_matches');
    }

    /**
     * @return array{ok: bool, message: string, score_domicile?: int, score_exterieur?: int, points?: int|null}
     */
    private function handleSubmit(
        Request $request,
        User $user,
        GameMatchRepository $gameMatchRepository,
        PronosticRepository $pronosticRepository,
        EntityManagerInterface $entityManager,
        PronosticScoringService $pronosticScoringService,
        BadgeEvaluator $badgeEvaluator,
        MatchStatusResolver $matchStatusResolver,
    ): array {
        $matchId = (int) $request->request->get('match_id');

        $match = $gameMatchRepository->find($matchId);
        if (!$match instanceof GameMatch) {
            return ['ok' => false, 'message' => 'Match introuvable.'];
        }

        return $this->savePronostic(
            $request,
            $user,
            $match,
            $pronosticRepository,
            $entityManager,
            $pronosticScoringService,
            $badgeEvaluator,
            $matchStatusResolver,
        );
    }

    /**
     * @return array{ok: bool, message: string, score_domicile?: int, score_exterieur?: int, points?: int|null}
     */
    private function savePronostic(
        Request $request,
        User $user,
        GameMatch $match,
        PronosticRepository $pronosticRepository,
        EntityManagerInterface $entityManager,
        PronosticScoringService $pronosticScoringService,
        BadgeEvaluator $badgeEvaluator,
        MatchStatusResolver $matchStatusResolver,
    ): array {
        if (!$user->isCotisationPayee()) {
            return ['ok' => false, 'message' => 'Réglez votre cotisation (10 € par joueur) pour pouvoir pronostiquer.'];
        }

        if (!$matchStatusResolver->canEditBeforeKickoff($match)) {
            return ['ok' => false, 'message' => 'Ce match a déjà commencé, le pronostic ne peut plus être modifié.'];
        }

        $scoreDomicile = $request->request->get('score_domicile');
        $scoreExterieur = $request->request->get('score_exterieur');

        if (!is_numeric($scoreDomicile) || !is_numeric($scoreExterieur)) {
            return ['ok' => false, 'message' => 'Merci de saisir deux scores valides.'];
        }

        $domicile = (int) $scoreDomicile;
        $exterieur = (int) $scoreExterieur;
        if ($domicile < 0 || $exterieur < 0) {
            return ['ok' => false, 'message' => 'Les scores doivent être positifs.'];
        }

        $pronostic = $pronosticRepository->findOneBy([
            'joueur' => $user,
            'match' => $match,
        ]);

        if (!$pronostic instanceof Pronostic) {
            $pronostic = new Pronostic();
            $pronostic->setJoueur($user);
            $pronostic->setMatch($match);
            $entityManager->persist($pronostic);
        }

        $pronostic
            ->setScoreDomicile($domicile)
            ->setScoreExterieur($exterieur);

        $pronosticScoringService->scorePronostic($pronostic);

        $entityManager->flush();
        $badgeEvaluator->evaluateOnPronosticSaved($user, $match);

        return [
            'ok' => true,
            'message' => 'Pronostic enregistré.',
            'score_domicile' => $domicile,
            'score_exterieur' => $exterieur,
            'points' => $pronostic->getPoints(),
        ];
    }

    private function wantsJson(Request $request): bool
    {
        if ($request->isXmlHttpRequest()) {
            return true;
        }

        $accept = $request->headers->get('Accept', '');

        return str_contains($accept, 'application/json');
    }

    /**
     * @param array{ok: bool, message: string, score_domicile?: int, score_exterieur?: int, points?: int|null} $result
     */
    private function jsonSaveResult(array $result): JsonResponse
    {
        $status = ($result['ok'] ?? false) ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST;

        return new JsonResponse($result, $status);
    }

    /**
     * @param array{ok: bool, message: string} $result
     */
    private function applyFlashFromResult(array $result): void
    {
        if ($result['ok'] ?? false) {
            $this->addFlash('success', $result['message']);

            return;
        }

        $this->addFlash('danger', $result['message']);
    }
}
