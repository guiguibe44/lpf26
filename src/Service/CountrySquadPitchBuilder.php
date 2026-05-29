<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Buteur;
use App\Repository\ButeurRepository;

/**
 * Construit la compo « terrain » d'un pays : gardiens + 3 zones (défense, milieu, attaque),
 * avec sous-groupes par poste exact pour rapprocher visuellement les joueurs au même rôle.
 */
final class CountrySquadPitchBuilder
{
    private const ZONES = [
        'defense' => 'Défense',
        'milieu' => 'Milieu',
        'attaque' => 'Attaque',
    ];

    public function __construct(
        private readonly ButeurRepository $buteurRepository,
    ) {
    }

    /**
     * @return array{
     *     goalkeepers: list<Buteur>,
     *     zones: list<array{
     *         key: string,
     *         label: string,
     *         groups: list<array{position: string, players: list<Buteur>}>
     *     }>,
     *     roster_sections: list<array{key: string, label: string, players: list<Buteur>}>,
     *     players: list<Buteur>,
     *     unplaced: list<Buteur>,
     *     total: int,
     *     placed_count: int,
     *     has_positions: bool
     * }
     */
    public function build(int $countryId): array
    {
        /** @var list<Buteur> $players */
        $players = $this->buteurRepository->findByCountryForPitch($countryId);

        $goalkeepers = [];
        $zoneBuckets = array_fill_keys(array_keys(self::ZONES), []);
        $unplaced = [];

        foreach ($players as $player) {
            $lineKey = $this->resolveLineKey($player->getPosition());
            if (null === $lineKey) {
                $unplaced[] = $player;

                continue;
            }
            if ('gardien' === $lineKey) {
                $goalkeepers[] = $player;

                continue;
            }
            $zoneBuckets[$lineKey][] = $player;
        }

        $zones = [];
        $placedCount = \count($goalkeepers);
        foreach (self::ZONES as $key => $label) {
            $groups = $this->groupByExactPosition($zoneBuckets[$key]);
            $zones[] = [
                'key' => $key,
                'label' => $label,
                'groups' => $groups,
            ];
            foreach ($groups as $group) {
                $placedCount += \count($group['players']);
            }
        }

        $rosterSections = $this->buildRosterSections($zoneBuckets, $goalkeepers, $unplaced);

        return [
            'goalkeepers' => $goalkeepers,
            'zones' => $zones,
            'roster_sections' => $rosterSections,
            'players' => $this->flattenRosterSections($rosterSections),
            'unplaced' => $unplaced,
            'total' => \count($players),
            'placed_count' => $placedCount,
            'has_positions' => $placedCount > 0,
        ];
    }

    /**
     * @param list<Buteur> $players
     *
     * @return list<array{position: string, players: list<Buteur>}>
     */
    private function groupByExactPosition(array $players): array
    {
        $byPosition = [];
        foreach ($players as $player) {
            $position = trim((string) $player->getPosition());
            $key = '' === $position ? '__unknown__' : mb_strtolower($position);
            if (!isset($byPosition[$key])) {
                $byPosition[$key] = [
                    'position' => $position,
                    'players' => [],
                ];
            }
            $byPosition[$key]['players'][] = $player;
        }

        return array_values($byPosition);
    }

    /**
     * @param array<string, list<Buteur>> $zoneBuckets
     * @param list<Buteur>                $goalkeepers
     * @param list<Buteur>                $unplaced
     *
     * @return list<array{key: string, label: string, players: list<Buteur>}>
     */
    private function buildRosterSections(array $zoneBuckets, array $goalkeepers, array $unplaced): array
    {
        $sections = [];

        foreach (['attaque', 'milieu', 'defense'] as $key) {
            $sectionPlayers = $this->sortPlayersInSection($zoneBuckets[$key]);
            if ([] === $sectionPlayers) {
                continue;
            }
            $sections[] = [
                'key' => $key,
                'label' => self::ZONES[$key],
                'players' => $sectionPlayers,
            ];
        }

        $keepers = $this->sortPlayersInSection($goalkeepers);
        if ([] !== $keepers) {
            $sections[] = [
                'key' => 'gardien',
                'label' => 'Gardiens',
                'players' => $keepers,
            ];
        }

        $withoutPosition = $this->sortPlayersInSection($unplaced);
        if ([] !== $withoutPosition) {
            $sections[] = [
                'key' => 'unplaced',
                'label' => 'Poste non renseigné',
                'players' => $withoutPosition,
            ];
        }

        return $sections;
    }

    /**
     * @param list<array{key: string, label: string, players: list<Buteur>}> $sections
     *
     * @return list<Buteur>
     */
    private function flattenRosterSections(array $sections): array
    {
        $players = [];
        foreach ($sections as $section) {
            foreach ($section['players'] as $player) {
                $players[] = $player;
            }
        }

        return $players;
    }

    /**
     * @param list<Buteur> $players
     *
     * @return list<Buteur>
     */
    private function sortPlayersInSection(array $players): array
    {
        usort($players, function (Buteur $a, Buteur $b): int {
            $numA = $a->getNumero() ?? PHP_INT_MAX;
            $numB = $b->getNumero() ?? PHP_INT_MAX;
            if ($numA !== $numB) {
                return $numA <=> $numB;
            }

            $nomCmp = strcasecmp($a->getNom() ?? '', $b->getNom() ?? '');
            if (0 !== $nomCmp) {
                return $nomCmp;
            }

            return strcasecmp($a->getPrenom() ?? '', $b->getPrenom() ?? '');
        });

        return $players;
    }

    private function resolveLineKey(?string $position): ?string
    {
        if (null === $position || '' === trim($position)) {
            return null;
        }

        $normalized = mb_strtolower(trim($position));

        return match (true) {
            str_contains($normalized, 'goal'), str_contains($normalized, 'keeper'), 'g' === $normalized, 'gk' === $normalized => 'gardien',
            str_contains($normalized, 'defen'), 'd' === $normalized => 'defense',
            str_contains($normalized, 'midfield'), str_contains($normalized, 'milieu'), 'm' === $normalized => 'milieu',
            str_contains($normalized, 'attack'), str_contains($normalized, 'forward'), str_contains($normalized, 'striker'), 'f' === $normalized, 'a' === $normalized => 'attaque',
            default => null,
        };
    }
}
