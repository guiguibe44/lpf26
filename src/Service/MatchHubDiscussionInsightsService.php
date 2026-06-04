<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GameMatch;
use App\Entity\Pronostic;
use App\Repository\TeamMemberRepository;
use App\Repository\UserRepository;

/**
 * Textes automatiques pour le fil du match (stats pronos, cotes, buteurs).
 */
final class MatchHubDiscussionInsightsService
{
    private const int MAX_TEAM_NAMES = 4;

    public function __construct(
        private readonly MatchCotePreviewService $matchCotePreviewService,
        private readonly MatchCoteExactScoreCalculator $matchCoteExactScoreCalculator,
        private readonly TeamMemberRepository $teamMemberRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * Messages auto juste après le message d’accueil (avant le coup d’envoi).
     *
     * @param list<Pronostic> $pronostics
     *
     * @return list<string>
     */
    public function buildPreKickoffInsightTexts(GameMatch $match, array $pronostics): array
    {
        $texts = [];
        $spread = $this->analyzePronosticSpread($pronostics);

        if (null !== $spread['most_played']) {
            $mp = $spread['most_played'];
            $texts[] = sprintf(
                'Prono le plus joué : %s (%d pronostic%s).',
                $mp['label'],
                $mp['count'],
                $mp['count'] > 1 ? 's' : '',
            );
        }

        $isolated = $spread['isolated'];
        if ([] !== $isolated) {
            $parts = [];
            foreach (\array_slice($isolated, 0, 3) as $row) {
                $coef = null !== $row['coefficient']
                    ? ' (cote ×' . number_format($row['coefficient'], 2, ',', ' ') . ')'
                    : '';
                $parts[] = $row['label'] . $coef;
            }
            $texts[] = 'Pronos les plus isolés : ' . implode(', ', $parts) . '.';
        }

        $cotes = $this->matchCotePreviewService->computeForMatch($match, $pronostics);
        if (($cotes['pronostics_count'] ?? 0) > 0) {
            $min = $cotes['min'] ?? null;
            $moy = $cotes['moyenne'] ?? null;
            $max = $cotes['max'] ?? null;
            if (null !== $min && null !== $moy && null !== $max) {
                $texts[] = sprintf(
                    'Cotes sur ce match : min ×%s · moy ×%s · max ×%s (%d pronos).',
                    $this->formatCoef($min),
                    $this->formatCoef($moy),
                    $this->formatCoef($max),
                    (int) $cotes['pronostics_count'],
                );
            }
        }

        return $texts;
    }

    /**
     * Message auto après un but si des joueurs ont ce buteur.
     *
     * @param array{buteur_id?: int, name: string, minute?: ?int} $goal
     */
    public function buildButeurGoalInsightText(array $goal): ?string
    {
        $buteurId = isset($goal['buteur_id']) ? (int) $goal['buteur_id'] : 0;
        if ($buteurId <= 0) {
            return null;
        }

        $selections = $this->userRepository->countWithButeurChoisiId($buteurId);
        if ($selections <= 0) {
            return null;
        }

        $teamNames = $this->teamMemberRepository->findTeamNamesWithButeurChoisi($buteurId, self::MAX_TEAM_NAMES + 1);
        $teamsCount = \count($teamNames);
        $teamLabel = $this->formatTeamList($teamNames);

        $teamsSuffix = $teamsCount > 0
            ? sprintf(' (%d équipe%s : %s)', $teamsCount, $teamsCount > 1 ? 's' : '', $teamLabel)
            : '';

        return sprintf(
            '%s marque ! %d joueur%s ont ce buteur%s.',
            trim((string) ($goal['name'] ?? 'Buteur')),
            $selections,
            $selections > 1 ? 's' : '',
            $teamsSuffix,
        );
    }

    /**
     * @param list<Pronostic> $pronostics
     *
     * @return array{
     *     most_played: ?array{label: string, count: int, home: int, away: int},
     *     isolated: list<array{label: string, coefficient: ?float, home: int, away: int}>
     * }
     */
    public function analyzePronosticSpread(array $pronostics): array
    {
        $counts = [];
        foreach ($pronostics as $pronostic) {
            if (!$pronostic instanceof Pronostic) {
                continue;
            }
            $home = $pronostic->getScoreDomicile();
            $away = $pronostic->getScoreExterieur();
            if (null === $home || null === $away) {
                continue;
            }
            $key = sprintf('%d-%d', $home, $away);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        if ([] === $counts) {
            return ['most_played' => null, 'isolated' => []];
        }

        arsort($counts);
        $topKey = (string) array_key_first($counts);
        [$topHome, $topAway] = array_map(intval(...), explode('-', $topKey, 2));

        $mostPlayed = [
            'label' => sprintf('%d-%d', $topHome, $topAway),
            'count' => (int) $counts[$topKey],
            'home' => $topHome,
            'away' => $topAway,
        ];

        $isolated = [];
        foreach ($counts as $scoreKey => $count) {
            if (1 !== $count) {
                continue;
            }
            [$home, $away] = array_map(intval(...), explode('-', (string) $scoreKey, 2));
            $isolated[] = [
                'label' => sprintf('%d-%d', $home, $away),
                'coefficient' => $this->matchCoteExactScoreCalculator->coefficientForPredictedScore(
                    $home,
                    $away,
                    $pronostics,
                ),
                'home' => $home,
                'away' => $away,
            ];
        }

        usort($isolated, static function (array $a, array $b): int {
            $coefA = $a['coefficient'] ?? 0.0;
            $coefB = $b['coefficient'] ?? 0.0;

            return $coefB <=> $coefA;
        });

        return [
            'most_played' => $mostPlayed,
            'isolated' => $isolated,
        ];
    }

    /**
     * @param list<string> $teamNames
     */
    private function formatTeamList(array $teamNames): string
    {
        if ([] === $teamNames) {
            return '—';
        }

        $shown = \array_slice($teamNames, 0, self::MAX_TEAM_NAMES);
        $label = implode(', ', $shown);
        if (\count($teamNames) > self::MAX_TEAM_NAMES) {
            $label .= '…';
        }

        return $label;
    }

    private function formatCoef(float $value): string
    {
        return number_format($value, 2, ',', ' ');
    }
}
