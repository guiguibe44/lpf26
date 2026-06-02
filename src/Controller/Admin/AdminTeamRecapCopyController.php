<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Joker;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Repository\TeamRepository;
use App\Repository\TeamRecapGifRepository;
use App\Service\LpfEmailRenderer;
use App\Service\TeamRecapCopyCatalog;
use App\Service\TeamRecapGifPicker;
use App\Service\TeamRecapMailer;
use App\TeamRecap\TeamRecapGifSlot;
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
    public function emailSimulator(
        Request $request,
        TeamRecapGifRepository $teamRecapGifRepository,
        TeamRepository $teamRepository,
    ): Response
    {
        $teams = $teamRepository->findAllWithMembersAndPlayers();
        [$teamChoices, $memberChoicesByTeam] = $this->buildTeamAndMemberChoices($teams);
        $defaultTeamId = array_key_first($teamChoices);
        $defaultMemberId = null !== $defaultTeamId
            ? (array_key_first($memberChoicesByTeam[$defaultTeamId] ?? []) ?: null)
            : null;

        $defaultQuery = [
            'subject' => 'hot',
            'laggard' => 'low',
            'ranking' => 'up',
            'jokers' => 'both',
            'goals' => '1',
            'bigballs' => '1',
            'gif_mode' => 'auto',
            'gif_slot' => '',
            'team_id' => null !== $defaultTeamId ? (string) $defaultTeamId : '',
            'member_id' => null !== $defaultMemberId ? (string) $defaultMemberId : '',
        ];

        $query = array_merge($defaultQuery, $request->query->all());

        return $this->render('admin/team_recap_email_simulator.html.twig', [
            'preview_url' => $this->generateUrl('admin_team_recap_email_preview'),
            'query' => $query,
            'gif_slots' => $teamRecapGifRepository->findActiveSlots(),
            'team_choices' => $teamChoices,
            'member_choices_by_team' => $memberChoicesByTeam,
        ]);
    }

    #[Route('/admin/communication/recap-equipe-apercu', name: 'admin_team_recap_email_preview', methods: ['GET'])]
    public function emailPreview(
        Request $request,
        TeamRecapCopyCatalog $catalog,
        LpfEmailRenderer $lpfEmailRenderer,
        TeamRecapMailer $mailer,
        UrlGeneratorInterface $urlGenerator,
        TeamRecapGifPicker $teamRecapGifPicker,
        TeamRepository $teamRepository,
    ): Response {
        $recap = $catalog->buildSampleRecapContext();
        $nickname = $this->applyPreviewFilters($recap, $request, $teamRecapGifPicker, $teamRepository);

        $html = $lpfEmailRenderer->render('email/content/team_recap.html.twig', [
            'pageTitle' => $mailer->buildSubject($recap),
            'nickname' => $nickname,
            'recap' => $recap,
            'teamShowUrl' => $urlGenerator->generate('app_ranking', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'rankingUrl' => $urlGenerator->generate('app_ranking', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'accountNotificationsUrl' => $urlGenerator->generate('app_account', [], UrlGeneratorInterface::ABSOLUTE_URL).'#notifications',
            'footerNote' => 'Aperçu admin — récap d’équipe LPF\'26.',
        ]);

        $sendTestUrl = $this->generateUrl('admin_team_recap_email_send_test', $request->query->all());

        return new Response(
            '<div style="position:sticky;top:0;z-index:10;background:#fff;border-bottom:1px solid #e5e7eb;padding:10px 14px;font-family:Arial,Helvetica,sans-serif;">'
            .'<a href="'.$sendTestUrl.'" style="display:inline-block;background:#16a34a;color:#fff;text-decoration:none;padding:8px 12px;border-radius:8px;font-weight:600;">Envoyer un mail test (à moi)</a>'
            .'</div>'
            .$html,
        );
    }

    #[Route('/admin/communication/recap-equipe-apercu/envoyer-test', name: 'admin_team_recap_email_send_test', methods: ['GET'])]
    public function sendPreviewTestEmail(
        Request $request,
        TeamRecapCopyCatalog $catalog,
        TeamRecapMailer $mailer,
        TeamRecapGifPicker $teamRecapGifPicker,
        TeamRepository $teamRepository,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || '' === trim((string) $user->getEmail())) {
            $this->addFlash('danger', 'Impossible d’envoyer le test : utilisateur admin sans e-mail.');

            return $this->redirectToRoute('admin_team_recap_email_simulator', $request->query->all());
        }

        $recap = $catalog->buildSampleRecapContext();
        $nickname = $this->applyPreviewFilters($recap, $request, $teamRecapGifPicker, $teamRepository);
        $mailer->send($user, $nickname, $recap);

        $this->addFlash('success', 'Mail test envoyé à '.$user->getEmail().'.');

        return $this->redirectToRoute('admin_team_recap_email_simulator', $request->query->all());
    }

    /**
     * @param array<string, mixed> $recap
     */
    private function applyPreviewFilters(
        array &$recap,
        Request $request,
        TeamRecapGifPicker $teamRecapGifPicker,
        TeamRepository $teamRepository,
    ): string
    {
        $nickname = 'Pilou';
        $selectedNickname = 'Pilou';
        $otherNickname = 'Zaza';
        $teamId = (int) $request->query->get('team_id', 0);
        if ($teamId > 0) {
            $team = $teamRepository->findOneWithMembersAndPlayers($teamId);
            if ($team instanceof Team) {
                $recap['team_id'] = (int) $team->getId();
                $recap['team_name'] = (string) ($team->getName() ?? $recap['team_name']);
                $memberId = (int) $request->query->get('member_id', 0);
                $firstMemberNickname = null;

                foreach ($team->getMembers() as $member) {
                    if (!$member instanceof TeamMember) {
                        continue;
                    }

                    $candidate = (string) ($member->getNickname() ?? '');
                    if ('' !== $candidate) {
                        if (null === $firstMemberNickname) {
                            $firstMemberNickname = $candidate;
                        }
                        $otherNickname = $candidate;
                    }

                    if ($memberId > 0 && (int) $member->getId() === $memberId) {
                        $nickname = (string) ($member->getNickname() ?? $nickname);
                        $selectedNickname = $nickname;
                        if (isset($recap['laggard']) && \is_array($recap['laggard'])) {
                            $recap['laggard']['nickname'] = $nickname;
                        }
                    } elseif ('' !== $candidate) {
                        $otherNickname = $candidate;
                    }
                }

                if ($memberId <= 0 && null !== $firstMemberNickname && '' !== trim($firstMemberNickname)) {
                    $nickname = $firstMemberNickname;
                    $selectedNickname = $firstMemberNickname;
                    if (isset($recap['laggard']) && \is_array($recap['laggard'])) {
                        $recap['laggard']['nickname'] = $nickname;
                    }
                }

            }
        }

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
            default => [[
                'name' => 'Double équipe',
                'match' => 'France — Allemagne',
                'team_points' => (int) ($recap['matches'][0]['team_points'] ?? 0),
            ]],
        };
        $recap['jokers_suffered'] = match ($jokers) {
            'none', 'placed' => [],
            default => [['name' => 'Pique de points', 'match' => 'France — Allemagne', 'blocked' => true]],
        };

        $subjectSlot = TeamRecapGifSlot::subjectCodeForTeamPoints((int) $recap['total_team_points']);
        $gifMode = (string) $request->query->get('gif_mode', 'auto');
        $slot = match ($gifMode) {
            'none' => null,
            'subject' => $subjectSlot,
            'joker_useful' => TeamRecapGifSlot::jokerUseful(Joker::CODE_DOUBLE_EQUIPE),
            'joker_not_useful' => TeamRecapGifSlot::jokerNotUseful(Joker::CODE_PIQUE_POINTS),
            'slot' => trim((string) $request->query->get('gif_slot', '')),
            default => [] !== $recap['jokers_placed']
                ? TeamRecapGifSlot::jokerUseful(Joker::CODE_DOUBLE_EQUIPE)
                : $subjectSlot,
        };

        $recap['recap_gif_url'] = null;
        if (null !== $slot && '' !== $slot) {
            $recap['recap_gif_url'] = $teamRecapGifPicker->pickRandomAbsoluteUrl($slot);
        }
        if (null === $recap['recap_gif_url'] || '' === $recap['recap_gif_url']) {
            $recap['recap_gif_url'] = $teamRecapGifPicker->pickRandomAbsoluteUrlAny();
        }

        $this->replaceSampleNames($recap, $selectedNickname, $otherNickname);

        return $nickname;
    }

    /**
     * @param array<string, mixed> $recap
     */
    private function replaceSampleNames(array &$recap, string $selectedNickname, string $otherNickname): void
    {
        $selectedNickname = '' !== trim($selectedNickname) ? $selectedNickname : 'Pilou';
        $otherNickname = '' !== trim($otherNickname) && $otherNickname !== $selectedNickname ? $otherNickname : 'Coéquipier';

        array_walk_recursive($recap, static function (&$value) use ($selectedNickname, $otherNickname): void {
            if (!\is_string($value)) {
                return;
            }

            $value = str_replace(
                ['Pilou', 'Zaza'],
                [$selectedNickname, $otherNickname],
                $value,
            );
        });
    }

    /**
     * @param list<Team> $teams
     *
     * @return array{0: array<int, string>, 1: array<int, array<int, string>>}
     */
    private function buildTeamAndMemberChoices(array $teams): array
    {
        $teamChoices = [];
        $memberChoicesByTeam = [];

        foreach ($teams as $team) {
            if (!$team instanceof Team || null === $team->getId()) {
                continue;
            }

            $tid = (int) $team->getId();
            $teamChoices[$tid] = (string) ($team->getName() ?? 'Équipe #'.$tid);
            $memberChoicesByTeam[$tid] = [];

            foreach ($team->getMembers() as $member) {
                if (!$member instanceof TeamMember || null === $member->getId()) {
                    continue;
                }

                $mid = (int) $member->getId();
                $email = trim((string) ($member->getPlayer()?->getEmail() ?? ''));
                $label = trim((string) ($member->getNickname() ?? 'Joueur'));
                if ('' !== $email) {
                    $label .= ' ('.$email.')';
                }
                $memberChoicesByTeam[$tid][$mid] = $label;
            }
        }

        return [$teamChoices, $memberChoicesByTeam];
    }
}
