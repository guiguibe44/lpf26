<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\JokerGuideBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class JokerGuideController extends AbstractController
{
    #[Route('/jokers', name: 'app_jokers', methods: ['GET'])]
    public function index(JokerGuideBuilder $jokerGuideBuilder): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('competition/jokers.html.twig', [
            'joker_catalog' => $jokerGuideBuilder->buildCatalog(),
        ]);
    }
}
