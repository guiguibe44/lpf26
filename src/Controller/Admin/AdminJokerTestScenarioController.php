<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\JokerTestScenarioDashboardBuilder;
use App\Service\JokerTestScenarioStepRunner;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminJokerTestScenarioController extends AbstractController
{
    #[Route('/admin/scenario-jokers', name: 'admin_joker_test_scenario', methods: ['GET'])]
    public function index(JokerTestScenarioDashboardBuilder $dashboardBuilder): Response
    {
        return $this->render('admin/joker_test_scenario.html.twig', [
            'dashboard' => $dashboardBuilder->build(),
        ]);
    }

    #[Route('/admin/scenario-jokers/avancer', name: 'admin_joker_test_scenario_advance', methods: ['POST'])]
    public function advance(Request $request, JokerTestScenarioStepRunner $stepRunner): Response
    {
        if (!$this->isCsrfTokenValid('joker_test_advance', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('admin_joker_test_scenario');
        }

        try {
            $result = $stepRunner->advance();
            $stepLabel = (string) ($result['step']['label'] ?? 'Étape');
            $this->addFlash('success', sprintf('Étape exécutée : %s', $stepLabel));
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_joker_test_scenario');
    }

    #[Route('/admin/scenario-jokers/reinitialiser', name: 'admin_joker_test_scenario_reset_progress', methods: ['POST'])]
    public function resetProgress(Request $request, JokerTestScenarioStepRunner $stepRunner): Response
    {
        if (!$this->isCsrfTokenValid('joker_test_reset', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('admin_joker_test_scenario');
        }

        try {
            $stepRunner->resetProgress();
            $this->addFlash('success', 'Progression et scores des matchs test remis à zéro (jokers conservés).');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_joker_test_scenario');
    }
}
