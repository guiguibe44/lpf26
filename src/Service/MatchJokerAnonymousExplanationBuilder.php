<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GameMatch;
use App\Entity\Joker;
use App\Entity\TeamJokerUsage;
use App\Repository\JokerRepository;
use App\Repository\TeamJokerUsageRepository;

/**
 * Explications des jokers actifs sur un match, sans révéler quelle équipe les a posés.
 */
final class MatchJokerAnonymousExplanationBuilder
{
    public function __construct(
        private readonly TeamJokerUsageRepository $teamJokerUsageRepository,
        private readonly JokerRepository $jokerRepository,
        private readonly JokerDefenseService $jokerDefenseService,
    ) {
    }

    /**
     * @return list<array{
     *     code: string,
     *     name: string,
     *     icon: string,
     *     image: ?string,
     *     summary: string,
     *     description: ?string,
     *     technical_lines: list<string>
     * }>
     */
    public function buildForMatch(GameMatch $match): array
    {
        $items = [];
        $seenCodes = [];

        foreach ($this->teamJokerUsageRepository->findByMatch($match) as $usage) {
            $joker = $usage->getJoker();
            $code = $joker?->getCode();
            if (null === $code || '' === $code) {
                continue;
            }

            if (Joker::CODE_COLLECTE_POINTS === $code) {
                continue;
            }

            if (Joker::CODE_ESPION === $code) {
                if (isset($seenCodes[$code])) {
                    continue;
                }

                $seenCodes[$code] = true;
                $items[] = $this->buildItem($usage, $match);

                continue;
            }

            $items[] = $this->buildItem($usage, $match);
        }

        if ([] !== $this->teamJokerUsageRepository->findCollecteTeamIdsForMatch($match)) {
            $items[] = $this->buildCollecteItem($match);
        }

        return $items;
    }

    /**
     * @return array{
     *     code: string,
     *     name: string,
     *     icon: string,
     *     image: ?string,
     *     summary: string,
     *     description: ?string,
     *     technical_lines: list<string>
     * }
     */
    private function buildItem(TeamJokerUsage $usage, GameMatch $match): array
    {
        $joker = $usage->getJoker();
        $code = (string) $joker?->getCode();
        $neutralized = $this->jokerDefenseService->isUsageNeutralized($usage);
        $hasTarget = null !== $usage->getTargetTeam();

        return [
            'code' => $code,
            'name' => $joker instanceof Joker ? $joker->getDisplayTitle() : $code,
            'icon' => Joker::tablerIconClassForCode($code),
            'image' => $joker?->getImage(),
            'summary' => $this->buildSummary($code, $neutralized, $hasTarget),
            'description' => $joker?->getDescription(),
            'technical_lines' => $joker instanceof Joker ? $joker->getTechnicalExplanationLines() : [],
        ];
    }

    /**
     * @return array{
     *     code: string,
     *     name: string,
     *     icon: string,
     *     image: ?string,
     *     summary: string,
     *     description: ?string,
     *     technical_lines: list<string>
     * }
     */
    private function buildCollecteItem(GameMatch $match): array
    {
        $collecteJoker = $this->jokerRepository->findOneBy(['code' => Joker::CODE_COLLECTE_POINTS]);
        $code = Joker::CODE_COLLECTE_POINTS;

        return [
            'code' => $code,
            'name' => $collecteJoker instanceof Joker ? $collecteJoker->getDisplayTitle() : 'Collecte de points',
            'icon' => Joker::tablerIconClassForCode($code),
            'image' => $collecteJoker?->getImage(),
            'summary' => 'Sur ce match, une équipe prélève 10 % des points équipe de chaque autre équipe (après les autres effets de jokers).',
            'description' => $collecteJoker?->getDescription(),
            'technical_lines' => $collecteJoker instanceof Joker ? $collecteJoker->getTechnicalExplanationLines() : [],
        ];
    }

    private function buildSummary(string $code, bool $neutralized, bool $hasTarget): string
    {
        if ($neutralized && JokerDefenseService::isOffensiveAgainstTeam($code)) {
            return 'Un joker offensif a été joué sur ce match, mais la cible était protégée : il est consommé sans effet.';
        }

        return match ($code) {
            Joker::CODE_DOUBLE_EQUIPE => 'Sur ce match, une équipe double ses points pronos : chaque joueur est noté avec le barème ×2 (cote incluse).',
            Joker::CODE_PIQUE_POINTS => $hasTarget
                ? 'Sur ce match, une équipe cible une adversaire : ses points match passent à 0 et l\'équipe qui joue ce joker récupère l\'ensemble.'
                : 'Sur ce match, le joker Pique points est actif entre deux équipes.',
            Joker::CODE_ESPION => 'Sur ce match, au moins une équipe a joué l\'Espion : cotes du match et jokers déjà posés visibles (sans révéler les pronos).',
            Joker::CODE_DOUBLE_BUTEUR => 'Sur ce match, une équipe double les points buteur gagnés par ses joueurs sur cette rencontre.',
            Joker::CODE_INVERSE_BUTEUR => $hasTarget
                ? 'Sur ce match, une équipe cible une adversaire dont un buteur est en lice : les points buteur de la cible deviennent négatifs.'
                : 'Sur ce match, le joker Inversion buteur est actif.',
            Joker::CODE_INVERSE_SCORE => $hasTarget
                ? 'Sur ce match, une équipe cible une adversaire : ses pronostics sont notés comme si le score réel était inversé (ex. 2-1 lu 1-2).'
                : 'Sur ce match, le joker Inversion score est actif.',
            Joker::CODE_BOUCLIER => 'Sur ce match, une équipe est protégée pour la journée : les jokers offensifs qui la ciblent sont neutralisés.',
            default => 'Un joker est actif sur ce match.',
        };
    }
}
