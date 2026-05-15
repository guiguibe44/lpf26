<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Buteur;
use App\Entity\User;
use App\Repository\ButeurRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ApiButeurController extends AbstractController
{
    #[Route('/api/buteurs/recherche', name: 'app_api_buteurs_search', methods: ['GET'])]
    public function search(Request $request, ButeurRepository $buteurRepository): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $q = $request->query->getString('q');
        $paysRaw = $request->query->get('pays_id');
        $paysId = is_numeric($paysRaw) ? (int) $paysRaw : null;
        if (null !== $paysId && $paysId <= 0) {
            $paysId = null;
        }

        $rows = $buteurRepository->searchForPicker('' !== trim($q) ? $q : null, $paysId, 50);

        $buteurs = [];
        foreach ($rows as $b) {
            if (!$b instanceof Buteur) {
                continue;
            }
            $pays = $b->getPays();
            $buteurs[] = [
                'id' => $b->getId(),
                'prenom' => $b->getPrenom(),
                'nom' => $b->getNom(),
                'photo' => $b->getPhotoPublicPath(),
                'pays' => $pays ? [
                    'id' => $pays->getId(),
                    'nom' => $pays->getNom(),
                    'drapeau' => $pays->getDrapeauPublicPath(),
                ] : null,
            ];
        }

        return new JsonResponse(['buteurs' => $buteurs]);
    }
}
