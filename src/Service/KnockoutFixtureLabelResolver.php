<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Libellés français pour les codes FIFA (poules, meilleurs 3e, vainqueurs de match).
 */
final class KnockoutFixtureLabelResolver
{
    public function resolve(string $code): string
    {
        $code = trim($code);
        if ('' === $code) {
            return 'À déterminer';
        }

        if (1 === preg_match('/^(\d)([A-L])$/u', $code, $m)) {
            $position = match ($m[1]) {
                '1' => '1er',
                '2' => '2e',
                default => $m[1].'e',
            };

            return sprintf('%s du groupe %s', $position, $m[2]);
        }

        if (1 === preg_match('/^3([A-L]+)$/u', $code, $m)) {
            $letters = implode(', ', str_split($m[1]));

            return sprintf('Meilleur 3e (poules %s)', $letters);
        }

        if (1 === preg_match('/^W(\d+)$/u', $code, $m)) {
            return sprintf('Vainqueur du match %s', $m[1]);
        }

        if (1 === preg_match('/^RU(\d+)$/u', $code, $m)) {
            return sprintf('Perdant du match %s', $m[1]);
        }

        return $code;
    }
}
