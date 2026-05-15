<?php

namespace App\Controller\Admin;

use App\Repository\MatchReminderLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class PronosticReminderHistoryController extends AbstractController
{
    #[Route('/admin/reminders/pronostics', name: 'admin_pronostic_reminders_history', methods: ['GET'])]
    public function history(MatchReminderLogRepository $matchReminderLogRepository): Response
    {
        return $this->render('admin/pronostic_reminders_history.html.twig', [
            'reminder_history' => $matchReminderLogRepository->findRecent(100),
        ]);
    }
}
