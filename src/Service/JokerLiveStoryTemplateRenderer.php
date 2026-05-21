<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Joker;
use App\Enum\JokerLiveStoryCase;

/**
 * Rendu des modèles de phrases joker (variables {equipe}, {points}, etc.).
 */
final class JokerLiveStoryTemplateRenderer
{
    public const VARIABLES_HELP = <<<'TXT'
Variables disponibles (une phrase par ligne dans chaque champ) :
• {joker} — nom du joker (ex. La Mexicaine)
• {equipe_poseuse} — équipe qui pose le joker
• {equipe_cible} — équipe ciblée
• {equipe} — équipe concernée par la ligne (gain/perte de points)
• {points} — nombre de points (valeur absolue)
• {points_label} — « point » ou « points »
• {suffixe_buteurs} — vide ou « sur les buteurs » (cas neutre)
TXT;

    /**
     * @param array<string, scalar|null> $variables
     *
     * @return list<string>
     */
    public function render(?Joker $joker, JokerLiveStoryCase $case, array $variables): array
    {
        $templates = $this->resolveTemplates($joker);
        $raw = $templates[$case->value] ?? null;
        $lines = $this->normalizeLines($raw);

        if ([] === $lines) {
            $defaults = JokerLiveStoryTemplateDefaults::global();
            $lines = $this->normalizeLines($defaults[$case->value] ?? null);
        }

        if ([] === $lines) {
            return [];
        }

        $merged = array_merge($this->baseVariables($joker), $variables);

        return array_values(array_filter(array_map(
            fn (string $line): string => $this->interpolate($line, $merged),
            $lines,
        )));
    }

    /**
     * @return array<string, list<string>|string>
     */
    private function resolveTemplates(?Joker $joker): array
    {
        if (!$joker instanceof Joker) {
            return [];
        }

        return $joker->getLiveStoryTemplates();
    }

    /**
     * @return array<string, string>
     */
    private function baseVariables(?Joker $joker): array
    {
        return [
            'joker' => $joker instanceof Joker ? $joker->getDisplayTitle() : '',
        ];
    }

    /**
     * @param list<string>|string|null $raw
     *
     * @return list<string>
     */
    private function normalizeLines(mixed $raw): array
    {
        if (null === $raw) {
            return [];
        }

        if (\is_string($raw)) {
            $raw = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        }

        if (!\is_array($raw)) {
            return [];
        }

        $lines = [];
        foreach ($raw as $line) {
            if (!\is_string($line)) {
                continue;
            }

            $trimmed = trim($line);
            if ('' !== $trimmed) {
                $lines[] = $trimmed;
            }
        }

        return $lines;
    }

    /**
     * @param array<string, scalar|null> $variables
     */
    private function interpolate(string $template, array $variables): string
    {
        $replacements = [];
        foreach ($variables as $key => $value) {
            $replacements['{'.$key.'}'] = (string) $value;
        }

        return strtr($template, $replacements);
    }

    public static function pointsLabel(int $abs): string
    {
        return $abs > 1 ? 'points' : 'point';
    }
}
