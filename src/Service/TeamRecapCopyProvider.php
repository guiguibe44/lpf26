<?php

declare(strict_types=1);

namespace App\Service;

use App\Data\TeamRecapCopyDefaults;
use App\Entity\TeamRecapCopy;
use App\Enum\TeamRecapCopyCategory;
use App\Repository\TeamRecapCopyRepository;

/**
 * Textes du récap : base de données (admin) avec repli sur les valeurs par défaut.
 */
final class TeamRecapCopyProvider
{
    /** @var array<string, string>|null */
    private ?array $defaultBodies = null;

    public function __construct(
        private readonly TeamRecapCopyRepository $repository,
    ) {
    }

    /**
     * @param array<string, string|int> $replacements
     */
    public function line(string $code, array $replacements = []): string
    {
        $body = $this->repository->findActiveByCode($code)?->getBody()
            ?? $this->defaultBodies()[$code]
            ?? '';

        return $this->interpolate($body, $replacements);
    }

    /**
     * @param array<string, string|int> $replacements
     */
    public function randomFromCategory(
        TeamRecapCopyCategory $category,
        string $pickKey,
        array $replacements = [],
    ): string {
        $bodies = [];
        foreach ($this->repository->findActiveByCategoryOrdered($category) as $row) {
            $body = $row->getBody();
            if (null !== $body && '' !== trim($body)) {
                $bodies[] = $body;
            }
        }

        if ([] === $bodies) {
            $bodies = $this->defaultBodiesForCategory($category);
        }

        if ([] === $bodies) {
            return '';
        }

        $index = abs(crc32($pickKey)) % \count($bodies);

        return $this->interpolate($bodies[$index], $replacements);
    }

    /**
     * @return list<array{code: string, condition: string, body: string}>
     */
    public function catalogRowsForCategory(TeamRecapCopyCategory $category): array
    {
        $rows = [];
        foreach ($this->repository->findActiveByCategoryOrdered($category) as $entity) {
            $rows[] = [
                'code' => (string) $entity->getCode(),
                'condition' => (string) ($entity->getConditionHint() ?? $entity->getAdminLabel()),
                'body' => (string) $entity->getBody(),
            ];
        }

        if ([] !== $rows) {
            return $rows;
        }

        foreach (TeamRecapCopyDefaults::entries() as $entry) {
            if ($entry['category'] !== $category) {
                continue;
            }
            $rows[] = [
                'code' => $entry['code'],
                'condition' => (string) ($entry['conditionHint'] ?? $entry['adminLabel']),
                'body' => $entry['body'],
            ];
        }

        return $rows;
    }

    public function pickIntroLineNote(): string
    {
        return 'Une seule accroche est envoyée par e-mail : choix déterministe (nom d’équipe + période) parmi les variantes actives du palier.';
    }

    /**
     * @return array<string, string>
     */
    private function defaultBodies(): array
    {
        if (null === $this->defaultBodies) {
            $this->defaultBodies = TeamRecapCopyDefaults::bodiesByCode();
        }

        return $this->defaultBodies;
    }

    /**
     * @return list<string>
     */
    private function defaultBodiesForCategory(TeamRecapCopyCategory $category): array
    {
        $bodies = [];
        foreach (TeamRecapCopyDefaults::entries() as $entry) {
            if ($entry['category'] === $category) {
                $bodies[] = $entry['body'];
            }
        }

        return $bodies;
    }

    /**
     * @param array<string, string|int> $replacements
     */
    private function interpolate(string $body, array $replacements): string
    {
        $search = [];
        $replace = [];
        foreach ($replacements as $key => $value) {
            $search[] = '{'.$key.'}';
            $replace[] = (string) $value;
        }

        return str_replace($search, $replace, $body);
    }
}
