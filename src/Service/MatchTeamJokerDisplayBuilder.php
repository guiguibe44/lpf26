<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GameMatch;
use App\Entity\Joker;
use App\Entity\Team;
use App\Entity\TeamJokerUsage;
use App\Repository\JokerRepository;
use App\Repository\TeamJokerUsageRepository;

/**
 * Badges jokers par équipe pour le live / détail match (sans révéler la poseuse sur les effets subis).
 */
final class MatchTeamJokerDisplayBuilder
{
    public function __construct(
        private readonly TeamJokerUsageRepository $teamJokerUsageRepository,
        private readonly JokerRepository $jokerRepository,
        private readonly JokerDefenseService $jokerDefenseService,
    ) {
    }

    /**
     * @param list<int> $teamIds
     *
     * @return array<int, list<array{
     *     code: string,
     *     name: string,
     *     image: ?string,
     *     kind: string,
     *     label: string,
     *     description: ?string,
     *     technical_lines: list<string>
     * }>>
     */
    public function buildByTeamIdForMatch(GameMatch $match, array $teamIds): array
    {
        $byTeam = [];
        foreach ($teamIds as $teamId) {
            $byTeam[(int) $teamId] = [];
        }

        $collectorIds = $this->teamJokerUsageRepository->findCollecteTeamIdsForMatch($match);
        $hasCollecte = [] !== $collectorIds;

        foreach ($this->teamJokerUsageRepository->findByMatch($match) as $usage) {
            $this->applyUsage($match, $usage, $byTeam);
        }

        if ($hasCollecte) {
            $collecteCard = $this->cardForCode(Joker::CODE_COLLECTE_POINTS);
            foreach ($teamIds as $teamId) {
                $tid = (int) $teamId;
                if (\in_array($tid, $collectorIds, true)) {
                    continue;
                }

                $byTeam[$tid][] = $this->badge(
                    $collecteCard,
                    'incoming',
                    'Points prélevés (collecte)',
                );
            }
        }

        foreach ($byTeam as $teamId => $badges) {
            $byTeam[$teamId] = $this->dedupeBadges($badges);
        }

        return $byTeam;
    }

    /**
     * Cartes Espion sur la bannière score (une carte par pose sur ce match).
     *
     * @return list<array{
     *     code: string,
     *     name: string,
     *     image: ?string,
     *     kind: string,
     *     label: string,
     *     description: ?string,
     *     technical_lines: list<string>
     * }>
     */
    public function buildEspionBadgesForMatch(GameMatch $match): array
    {
        $usages = [];
        $teamIds = [];
        foreach ($this->teamJokerUsageRepository->findByMatch($match) as $usage) {
            if (Joker::CODE_ESPION !== $usage->getJoker()?->getCode()) {
                continue;
            }

            $usages[] = $usage;
            $teamId = $usage->getTeam()?->getId();
            if (null !== $teamId) {
                $teamIds[(int) $teamId] = true;
            }
        }

        if ([] === $usages) {
            return [];
        }

        $teamsCountLabel = $this->formatEspionTeamsCountLabel(\count($teamIds));
        $badges = [];
        foreach ($usages as $usage) {
            $badges[] = $this->badge(
                $this->cardFromUsage($usage),
                'own',
                $teamsCountLabel,
            );
        }

        return $badges;
    }

    private function formatEspionTeamsCountLabel(int $teamsCount): string
    {
        if ($teamsCount <= 0) {
            return '';
        }

        return 1 === $teamsCount ? '1 équipe' : $teamsCount.' équipes';
    }

    /**
     * @param array<int, list<array<string, mixed>>> $byTeam
     */
    private function applyUsage(GameMatch $match, TeamJokerUsage $usage, array &$byTeam): void
    {
        $placerId = (int) ($usage->getTeam()?->getId() ?? 0);
        $code = $usage->getJoker()?->getCode();
        if (null === $code || '' === $code) {
            return;
        }

        if (Joker::CODE_ESPION === $code) {
            return;
        }

        $card = $this->cardFromUsage($usage);

        if (JokerDefenseService::isOffensiveAgainstTeam($code)) {
            $target = $usage->getTargetTeam();
            $targetId = (int) ($target?->getId() ?? 0);
            if ($targetId <= 0) {
                return;
            }

            if ($this->jokerDefenseService->isUsageNeutralized($usage)) {
                $byTeam[$targetId][] = $this->shieldBadgeForTeam($target, $match);
            } else {
                $byTeam[$targetId][] = $this->badge($card, 'incoming', (string) $card['name']);
            }

            if ($placerId > 0) {
                $byTeam[$placerId][] = $this->badge($card, 'own', 'Votre joker');
            }

            return;
        }

        if ($placerId > 0) {
            $byTeam[$placerId][] = $this->badge($card, 'own', 'Votre joker');
        }
    }

    /**
     * @return array{
     *     code: string,
     *     name: string,
     *     image: ?string,
     *     description: ?string,
     *     technical_lines: list<string>
     * }
     */
    private function cardFromUsage(TeamJokerUsage $usage): array
    {
        return $this->cardFromJoker($usage->getJoker(), (string) ($usage->getJoker()?->getCode() ?? ''));
    }

    /**
     * @return array{
     *     code: string,
     *     name: string,
     *     image: ?string,
     *     description: ?string,
     *     technical_lines: list<string>
     * }
     */
    private function cardForCode(string $code): array
    {
        return $this->cardFromJoker($this->jokerRepository->findOneBy(['code' => $code]), $code);
    }

    /**
     * @return array{
     *     code: string,
     *     name: string,
     *     image: ?string,
     *     description: ?string,
     *     technical_lines: list<string>
     * }
     */
    private function cardFromJoker(?Joker $joker, string $codeFallback): array
    {
        if (!$joker instanceof Joker) {
            return [
                'code' => $codeFallback,
                'name' => $codeFallback,
                'image' => null,
                'icon' => Joker::tablerIconClassForCode($codeFallback),
                'description' => null,
                'technical_lines' => [],
            ];
        }

        $code = (string) $joker->getCode();

        return [
            'code' => $code,
            'name' => $joker->getDisplayTitle(),
            'image' => $joker->getImage(),
            'icon' => $joker->getTablerIconClass(),
            'description' => $joker->getDescription(),
            'technical_lines' => $joker->getTechnicalExplanationLines(),
        ];
    }

    /**
     * @param array{
     *     code: string,
     *     name: string,
     *     image: ?string,
     *     description: ?string,
     *     technical_lines: list<string>
     * } $card
     *
     * @return array{
     *     code: string,
     *     name: string,
     *     image: ?string,
     *     kind: string,
     *     label: string,
     *     description: ?string,
     *     technical_lines: list<string>
     * }
     */
    private function badge(array $card, string $kind, string $label): array
    {
        return [
            'code' => $card['code'],
            'name' => $card['name'],
            'image' => $card['image'],
            'icon' => $card['icon'] ?? Joker::tablerIconClassForCode($card['code']),
            'kind' => $kind,
            'label' => $label,
            'description' => $card['description'],
            'technical_lines' => $card['technical_lines'],
        ];
    }

    /**
     * @return array{code: string, name: string, image: ?string, kind: string, label: string}
     */
    private function shieldBadgeForTeam(Team $team, GameMatch $match): array
    {
        if ($this->jokerDefenseService->teamHasBouclierOnMatchday($team, $match)) {
            $card = $this->cardForCode(Joker::CODE_BOUCLIER);

            return $this->badge($card, 'shield', 'Bouclier — joker adverse neutralisé');
        }

        if ($this->jokerDefenseService->isTeamProtectedByFavoriteOnGroupMatch($team, $match)) {
            $card = $this->cardForCode(Joker::CODE_EQUIPE_FAVORITE);

            return $this->badge($card, 'shield', 'Équipe favorite — joker adverse neutralisé');
        }

        $card = $this->cardForCode(Joker::CODE_BOUCLIER);

        return $this->badge($card, 'shield', 'Protection active — joker adverse neutralisé');
    }

    /**
     * @param list<array{code: string, name: string, image: ?string, kind: string, label: string}> $badges
     *
     * @return list<array{code: string, name: string, image: ?string, kind: string, label: string}>
     */
    private function dedupeBadges(array $badges): array
    {
        $seen = [];
        $out = [];
        foreach ($badges as $badge) {
            $key = $badge['kind'].'|'.$badge['code'];
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $out[] = $badge;
        }

        return $out;
    }
}
