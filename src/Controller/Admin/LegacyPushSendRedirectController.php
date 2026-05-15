<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class LegacyPushSendRedirectController extends AbstractController
{
    #[Route('/admin/push/send', name: 'admin_push_send', methods: ['GET', 'POST'])]
    public function redirectToManualMessages(): RedirectResponse
    {
        return $this->redirectToRoute('admin_manual_messages', [], 301);
    }
}
