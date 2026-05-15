<?php

namespace App\Controller;

use App\Entity\GameMatch;
use App\Entity\Pronostic;
use App\Entity\User;
use App\Repository\GameMatchRepository;
use App\Repository\PronosticRepository;
use App\Service\PronosticScoringService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($request->isMethod('POST')) {
            $this->handleSubmit($request, $user, $gameMatchRepository, $pronosticRepository, $entityManager, $pronosticScoringService);

            return $this->redirectToRoute('app_pronostics');
        }

        $matches = $gameMatchRepository->findBy([], ['dateHeure' => 'ASC']);
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
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $this->savePronostic($request, $user, $match, $pronosticRepository, $entityManager, $pronosticScoringService);

        return $this->redirectToRoute('app_matches');
    }

    private function handleSubmit(
        Request $request,
        User $user,
        GameMatchRepository $gameMatchRepository,
        PronosticRepository $pronosticRepository,
        EntityManagerInterface $entityManager,
        PronosticScoringService $pronosticScoringService,
    ): void {
        $matchId = (int) $request->request->get('match_id');

        $match = $gameMatchRepository->find($matchId);
        if (!$match instanceof GameMatch) {
            $this->addFlash('danger', 'Match introuvable.');

            return;
        }

        $this->savePronostic($request, $user, $match, $pronosticRepository, $entityManager, $pronosticScoringService);
    }

    private function savePronostic(
        Request $request,
        User $user,
        GameMatch $match,
        PronosticRepository $pronosticRepository,
        EntityManagerInterface $entityManager,
        PronosticScoringService $pronosticScoringService,
    ): void {
        if (!$user->isCotisationPayee()) {
            $this->addFlash('danger', 'Réglez votre cotisation (10 € par joueur) pour pouvoir pronostiquer.');

            return;
        }

        if (!$this->canEditPronostic($match)) {
            $this->addFlash('danger', 'Ce match a déjà commencé, le pronostic ne peut plus être modifié.');

            return;
        }

        $scoreDomicile = $request->request->get('score_domicile');
        $scoreExterieur = $request->request->get('score_exterieur');

        if (!is_numeric($scoreDomicile) || !is_numeric($scoreExterieur)) {
            $this->addFlash('danger', 'Merci de saisir deux scores valides.');

            return;
        }

        $domicile = (int) $scoreDomicile;
        $exterieur = (int) $scoreExterieur;
        if ($domicile < 0 || $exterieur < 0) {
            $this->addFlash('danger', 'Les scores doivent être positifs.');

            return;
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
        $this->addFlash('success', 'Pronostic enregistré.');
    }

    private function canEditPronostic(GameMatch $match): bool
    {
        $dateHeure = $match->getDateHeure();

        return 'SCHEDULED' === $match->getStatut()
            && null !== $dateHeure
            && $dateHeure > new \DateTimeImmutable();
    }
}
