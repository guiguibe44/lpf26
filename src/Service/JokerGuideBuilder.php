<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Joker;
use App\Repository\JokerRepository;

/**
 * Contenu du guide public des jokers (données éditoriales en base + règles de jeu en code).
 */
final class JokerGuideBuilder
{
    public function __construct(
        private readonly JokerRepository $jokerRepository,
    ) {
    }

    /**
     * @return list<array{
     *     joker: Joker,
     *     tag: string|null,
     *     tag_label: string,
     *     tag_css: string,
     *     details: list<string>,
     *     irreversible: bool,
     *     targets_opponent: bool
     * }>
     */
    public function buildCatalog(): array
    {
        $entries = [];

        foreach ($this->jokerRepository->findAllOrdered() as $joker) {
            if (!$joker->isActive()) {
                continue;
            }

            $code = (string) $joker->getCode();
            $behavior = self::behaviorForCode($code);

            $entries[] = [
                'joker' => $joker,
                'tag' => $joker->getTag(),
                'tag_label' => $joker->getTagLabel(),
                'tag_css' => $joker->getTagCssClass(),
                'details' => $joker->getTechnicalExplanationLines(),
                'irreversible' => $behavior['irreversible'],
                'targets_opponent' => $behavior['targets_opponent'],
            ];
        }

        return $entries;
    }

    /**
     * @return array{irreversible: bool, targets_opponent: bool}
     */
    private static function behaviorForCode(string $code): array
    {
        return match ($code) {
            Joker::CODE_ESPION => ['irreversible' => true, 'targets_opponent' => false],
            Joker::CODE_PIQUE_POINTS,
            Joker::CODE_INVERSE_BUTEUR,
            Joker::CODE_INVERSE_SCORE => ['irreversible' => false, 'targets_opponent' => true],
            default => ['irreversible' => false, 'targets_opponent' => false],
        };
    }
}
