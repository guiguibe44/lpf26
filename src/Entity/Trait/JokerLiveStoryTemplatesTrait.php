<?php

declare(strict_types=1);

namespace App\Entity\Trait;

use App\Enum\JokerLiveStoryCase;
use Doctrine\ORM\Mapping as ORM;

/**
 * Modèles de phrases live (JSON) + champs formulaire admin (non mappés Doctrine).
 */
trait JokerLiveStoryTemplatesTrait
{
    /**
     * @var array<string, list<string>|string>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $liveStoryTemplates = null;

    /**
     * @return array<string, list<string>|string>
     */
    public function getLiveStoryTemplates(): array
    {
        return $this->liveStoryTemplates ?? [];
    }

    /**
     * @param array<string, list<string>|string>|null $liveStoryTemplates
     */
    public function setLiveStoryTemplates(?array $liveStoryTemplates): static
    {
        if (null === $liveStoryTemplates) {
            $this->liveStoryTemplates = null;

            return $this;
        }

        $normalized = [];
        foreach ($liveStoryTemplates as $key => $value) {
            if (!\is_string($key) || '' === $key) {
                continue;
            }

            $lines = $this->normalizeLiveStoryInput($value);
            if ([] !== $lines) {
                $normalized[$key] = 1 === \count($lines) ? $lines[0] : $lines;
            }
        }

        $this->liveStoryTemplates = [] === $normalized ? null : $normalized;

        return $this;
    }

    public function getLiveStoryPlaced(): string
    {
        return $this->formatLiveStoryCase(JokerLiveStoryCase::Placed);
    }

    public function setLiveStoryPlaced(?string $value): static
    {
        $this->applyLiveStoryCase(JokerLiveStoryCase::Placed, $value);

        return $this;
    }

    public function getLiveStoryPlacedOnTarget(): string
    {
        return $this->formatLiveStoryCase(JokerLiveStoryCase::PlacedOnTarget);
    }

    public function setLiveStoryPlacedOnTarget(?string $value): static
    {
        $this->applyLiveStoryCase(JokerLiveStoryCase::PlacedOnTarget, $value);

        return $this;
    }

    public function getLiveStoryShieldActive(): string
    {
        return $this->formatLiveStoryCase(JokerLiveStoryCase::ShieldActive);
    }

    public function setLiveStoryShieldActive(?string $value): static
    {
        $this->applyLiveStoryCase(JokerLiveStoryCase::ShieldActive, $value);

        return $this;
    }

    public function getLiveStoryNeutralized(): string
    {
        return $this->formatLiveStoryCase(JokerLiveStoryCase::Neutralized);
    }

    public function setLiveStoryNeutralized(?string $value): static
    {
        $this->applyLiveStoryCase(JokerLiveStoryCase::Neutralized, $value);

        return $this;
    }

    public function getLiveStoryPointsGain(): string
    {
        return $this->formatLiveStoryCase(JokerLiveStoryCase::PointsGain);
    }

    public function setLiveStoryPointsGain(?string $value): static
    {
        $this->applyLiveStoryCase(JokerLiveStoryCase::PointsGain, $value);

        return $this;
    }

    public function getLiveStoryPointsLoss(): string
    {
        return $this->formatLiveStoryCase(JokerLiveStoryCase::PointsLoss);
    }

    public function setLiveStoryPointsLoss(?string $value): static
    {
        $this->applyLiveStoryCase(JokerLiveStoryCase::PointsLoss, $value);

        return $this;
    }

    public function getLiveStoryPointsNeutral(): string
    {
        return $this->formatLiveStoryCase(JokerLiveStoryCase::PointsNeutral);
    }

    public function setLiveStoryPointsNeutral(?string $value): static
    {
        $this->applyLiveStoryCase(JokerLiveStoryCase::PointsNeutral, $value);

        return $this;
    }

    public function getLiveStoryPointsGainButeur(): string
    {
        return $this->formatLiveStoryCase(JokerLiveStoryCase::PointsGainButeur);
    }

    public function setLiveStoryPointsGainButeur(?string $value): static
    {
        $this->applyLiveStoryCase(JokerLiveStoryCase::PointsGainButeur, $value);

        return $this;
    }

    public function getLiveStoryPointsLossButeur(): string
    {
        return $this->formatLiveStoryCase(JokerLiveStoryCase::PointsLossButeur);
    }

    public function setLiveStoryPointsLossButeur(?string $value): static
    {
        $this->applyLiveStoryCase(JokerLiveStoryCase::PointsLossButeur, $value);

        return $this;
    }

    public function getLiveStoryEspion(): string
    {
        return $this->formatLiveStoryCase(JokerLiveStoryCase::Espion);
    }

    public function setLiveStoryEspion(?string $value): static
    {
        $this->applyLiveStoryCase(JokerLiveStoryCase::Espion, $value);

        return $this;
    }

    private function formatLiveStoryCase(JokerLiveStoryCase $case): string
    {
        $raw = $this->liveStoryTemplates[$case->value] ?? null;

        return implode("\n", $this->normalizeLiveStoryInput($raw));
    }

    private function applyLiveStoryCase(JokerLiveStoryCase $case, ?string $raw): void
    {
        $templates = $this->liveStoryTemplates ?? [];
        $lines = $this->normalizeLiveStoryInput($raw);

        if ([] === $lines) {
            unset($templates[$case->value]);
        } else {
            $templates[$case->value] = 1 === \count($lines) ? $lines[0] : $lines;
        }

        $this->liveStoryTemplates = [] === $templates ? null : $templates;
    }

    /**
     * @return list<string>
     */
    private function normalizeLiveStoryInput(mixed $raw): array
    {
        if (null === $raw) {
            return [];
        }

        if (\is_string($raw)) {
            $parts = preg_split('/\r\n|\r|\n/', $raw) ?: [];

            return array_values(array_filter(array_map(static fn (string $line): string => trim($line), $parts)));
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
}
