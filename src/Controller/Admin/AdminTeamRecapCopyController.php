<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\LpfEmailRenderer;
use App\Service\TeamRecapCopyCatalog;
use App\Service\TeamRecapMailer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminTeamRecapCopyController extends AbstractController
{
    #[Route('/admin/communication/recap-equipe-textes', name: 'admin_team_recap_copy_catalog', methods: ['GET'])]
    public function catalog(TeamRecapCopyCatalog $catalog): Response
    {
        return $this->render('admin/team_recap_copy_catalog.html.twig', [
            'catalog' => $catalog->buildAdminViewModel(),
            'preview_url' => $this->generateUrl('admin_team_recap_email_preview'),
        ]);
    }

    #[Route('/admin/communication/recap-equipe-apercu', name: 'admin_team_recap_email_preview', methods: ['GET'])]
    public function emailPreview(
        TeamRecapCopyCatalog $catalog,
        LpfEmailRenderer $lpfEmailRenderer,
        TeamRecapMailer $mailer,
        UrlGeneratorInterface $urlGenerator,
    ): Response {
        $recap = $catalog->buildSampleRecapContext();

        $html = $lpfEmailRenderer->render('email/content/team_recap.html.twig', [
            'pageTitle' => $mailer->buildSubject($recap),
            'nickname' => 'Pilou',
            'recap' => $recap,
            'teamShowUrl' => $urlGenerator->generate('app_ranking', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'rankingUrl' => $urlGenerator->generate('app_ranking', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'accountNotificationsUrl' => $urlGenerator->generate('app_account', [], UrlGeneratorInterface::ABSOLUTE_URL).'#notifications',
            'footerNote' => 'Aperçu admin — récap d’équipe LPF\'26.',
        ]);

        return new Response($html);
    }
}
