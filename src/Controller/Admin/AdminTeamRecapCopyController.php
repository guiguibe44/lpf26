<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\LpfEmailRenderer;
use App\Service\TeamRecapCopyCatalog;
use App\Service\TeamRecapMailer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
            'simulator_url' => $this->generateUrl('admin_team_recap_email_simulator'),
        ]);
    }

    #[Route('/admin/communication/recap-equipe-simulateur', name: 'admin_team_recap_email_simulator', methods: ['GET'])]
    public function emailSimulator(Request $request): Response
    {
        $defaultQuery = [
            'subject' => 'hot',
            'laggard' => 'low',
            'ranking' => 'up',
            'jokers' => 'both',
            'goals' => '1',
            'bigballs' => '1',
            'gif' => 'joker',
        ];

        $query = array_merge($defaultQuery, $request->query->all());

        return $this->render('admin/team_recap_email_simulator.html.twig', [
            'preview_url' => $this->generateUrl('admin_team_recap_email_preview'),
            'query' => $query,
        ]);
    }

    #[Route('/admin/communication/recap-equipe-apercu', name: 'admin_team_recap_email_preview', methods: ['GET'])]
    public function emailPreview(
        Request $request,
        TeamRecapCopyCatalog $catalog,
        LpfEmailRenderer $lpfEmailRenderer,
        TeamRecapMailer $mailer,
        UrlGeneratorInterface $urlGenerator,
    ): Response {
        $recap = $catalog->buildSampleRecapContext();
        $this->applyPreviewFilters($recap, $request);

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

    /**
     * @param array<string, mixed> $recap
     */
    private function applyPreviewFilters(array &$recap, Request $request): void
    {
        $subject = (string) $request->query->get('subject', 'hot');
        $recap['total_team_points'] = match ($subject) {
            'neutral' => 0,
            'positive' => 24,
            default => 87,
        };

        $laggardProfile = (string) $request->query->get('laggard', 'low');
        $laggardPoints = match ($laggardProfile) {
            'zero' => 0,
            'default' => 32,
            default => 8,
        };
        $recap['laggard']['points'] = $laggardPoints;

        $ranking = (string) $request->query->get('ranking', 'up');
        if ('none' === $ranking) {
            $recap['ranking'] = null;
            $recap['ranking_cheer'] = null;
        } else {
            $recap['ranking'] = match ($ranking) {
                'down' => [
                    'before' => ['position' => 9, 'total' => 399, 'teams_count' => 48],
                    'after' => ['position' => 12, 'total' => 364, 'teams_count' => 48],
                    'delta_positions' => -3,
                    'delta_points' => -35,
                ],
                'same' => [
                    'before' => ['position' => 12, 'total' => 312, 'teams_count' => 48],
                    'after' => ['position' => 12, 'total' => 344, 'teams_count' => 48],
                    'delta_positions' => 0,
                    'delta_points' => 32,
                ],
                default => [
                    'before' => ['position' => 12, 'total' => 312, 'teams_count' => 48],
                    'after' => ['position' => 9, 'total' => 399, 'teams_count' => 48],
                    'delta_positions' => 3,
                    'delta_points' => 87,
                ],
            };
        }

        $recap['goals'] = $request->query->getBoolean('goals', true) ? [
            ['nickname' => 'Zaza', 'buteur' => 'Mbappé', 'match' => 'France — Allemagne', 'minute' => 23, 'points' => 33],
        ] : [];

        $bigballsOn = $request->query->getBoolean('bigballs', true);
        $recap['bigballs_summary'] = $bigballsOn ? ['attempted' => 1, 'succeeded' => 1] : ['attempted' => 0, 'succeeded' => 0];
        if (isset($recap['matches'][0])) {
            $recap['matches'][0]['bigballs'] = ['attempted' => $bigballsOn, 'succeeded' => $bigballsOn];
            if (isset($recap['matches'][0]['players'][0])) {
                $recap['matches'][0]['players'][0]['bigballs'] = false;
            }
            if (isset($recap['matches'][0]['players'][1])) {
                $recap['matches'][0]['players'][1]['bigballs'] = $bigballsOn;
            }
        }

        $jokers = (string) $request->query->get('jokers', 'both');
        $recap['jokers_placed'] = match ($jokers) {
            'none', 'suffered' => [],
            default => [['name' => 'Double équipe', 'match' => 'France — Allemagne']],
        };
        $recap['jokers_suffered'] = match ($jokers) {
            'none', 'placed' => [],
            default => [['name' => 'Pique de points', 'match' => 'France — Allemagne', 'blocked' => true]],
        };

        $gif = (string) $request->query->get('gif', 'joker');
        $recap['recap_gif_url'] = match ($gif) {
            'none' => null,
            'subject' => 'https://media.giphy.com/media/3oEjI6SIIHBdRxXI40/giphy.gif',
            default => 'https://media.giphy.com/media/l0MYt5jPR6QX5pnqM/giphy.gif',
        };
    }
}
