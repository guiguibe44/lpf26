<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Buteur;
use App\Repository\ButeurRepository;

/**
 * Construit la compo « terrain » d'un pays : joueurs répartis par ligne
 * (gardiens, défenseurs, milieux, attaquants) à partir du poste renvoyé par l'API.
 */
final class CountrySquadPitchBuilder
{
    private const LINES = [
        'gardien' => 'Gardiens',
        'defenseur' => 'Défenseurs',
        'milieu' => 'Milieux',
        'attaquant' => 'Attaquants',
    ];

    public function __construct(
        private readonly ButeurRepository $buteurRepository,
    ) {
    }

    /**
     * @return array{
     *     lines: list<array{key: string, label: string, players: list<Buteur>}>,
     *     unplaced: list<Buteur>,
     *     total: int,
     *     placed_count: int,
     *     has_positions: bool
     * }
     */
    public function build(int $countryId): array
    {
        $players = $this->buteurRepository->findByCountryForPitch($countryId);

        $buckets = array_fill_keys(array_keys(self::LINES), []);
        $unplaced = [];
        foreach ($players as $player) {
            $lineKey = $this->resolveLineKey($player->getPosition());
            if (null === $lineKey) {
                $unplaced[] = $player;

                continue;
            }
            $buckets[$lineKey][] = $player;
        }

        $lines = [];
        $placedCount = 0;
        foreach (self::LINES as $key => $label) {
            $lines[] = [
                'key' => $key,
                'label' => $label,
                'players' => $buckets[$key],
            ];
            $placedCount += \count($buckets[$key]);
        }

        return [
            'lines' => $lines,
            'unplaced' => $unplaced,
            'total' => \count($players),
            'placed_count' => $placedCount,
            'has_positions' => $placedCount > 0,
        ];
    }

    private function resolveLineKey(?string $position): ?string
    {
        if (null === $position || '' === trim($position)) {
            return null;
        }

        $normalized = mb_strtolower(trim($position));

        return match (true) {
            str_contains($normalized, 'goal'), str_contains($normalized, 'keeper'), 'g' === $normalized, 'gk' === $normalized => 'gardien',
            str_contains($normalized, 'defen'), 'd' === $normalized => 'defenseur',
            str_contains($normalized, 'midfield'), str_contains($normalized, 'milieu'), 'm' === $normalized => 'milieu',
            str_contains($normalized, 'attack'), str_contains($normalized, 'forward'), str_contains($normalized, 'striker'), 'f' === $normalized, 'a' === $normalized => 'attaquant',
            default => null,
        };
    }
}
