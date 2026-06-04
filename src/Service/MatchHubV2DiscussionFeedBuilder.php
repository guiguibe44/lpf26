<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GameMatch;
use App\Entity\Pronostic;

/**
 * Fusionne messages, buts et alertes auto en un fil chronologique pour le hub match v2.
 */
final class MatchHubV2DiscussionFeedBuilder
{
    private const string AUTO_AUTHOR = 'LPF\'26';

    public function __construct(
        private readonly MatchHubDiscussionInsightsService $insightsService,
    ) {
    }

    /**
     * Fil fictif démo France — Allemagne (ordre narratif + messages auto).
     *
     * @return list<array<string, mixed>>
     */
    public function buildDemoFeed(
        string $state,
        string $homeLabel,
        string $awayLabel,
        string $partnerName,
    ): array {
        if ('avenir' === $state) {
            return $this->sortFeed([
                $this->message(null, 0, 'Organisateur', false, 'Bon match à tous — pronos verrouillés au coup d’envoi.'),
                $this->auto(null, 1, 'Prono le plus joué : 2-1 (14 pronos).'),
                $this->auto(null, 2, 'Pronos les plus isolés : 3-0 (cote ×4,50), 0-3 (cote ×4,17).'),
                $this->auto(null, 3, 'Cotes sur ce match : min ×1,20 · moy ×2,35 · max ×4,50 (28 pronos).'),
                $this->message(null, 4, 'Vous', true, 'On vise le 2-1 ce soir.'),
                $this->message(null, 5, $partnerName, false, 'J’ai mis le Miroir, on verra…'),
            ]);
        }

        $items = [
            $this->message(null, 0, 'Organisateur', false, 'Bon match à tous — pronos verrouillés au coup d’envoi.'),
            $this->auto(null, 1, 'Prono le plus joué : 2-1 (14 pronos).'),
            $this->auto(null, 2, 'Pronos les plus isolés : 3-0 (cote ×4,50), 1-3 (cote ×3,50).'),
            $this->auto(null, 3, 'Cotes sur ce match : min ×1,20 · moy ×2,35 · max ×4,50 (28 pronos).'),
            $this->system(4, 'Coup d\'envoi'),
            $this->message(12, 5, 'Vous', true, 'Allez les bleus !'),
            $this->goal(23, $homeLabel, 'K. Mbappé', 'home', '1-0'),
            $this->auto(23, 24, 'K. Mbappé marque ! 8 joueurs ont ce buteur (4 équipes : Les Bleus du Désert, Équipe France, Les Girondins, FC Mbappé…).'),
            $this->message(24, 25, 'Sophie', false, 'Mbappé encore au rendez-vous.'),
            $this->goal(41, $awayLabel, 'F. Wirtz', 'away', '1-1'),
            $this->auto(41, 42, 'F. Wirtz marque ! 3 joueurs ont ce buteur (2 équipes : Mannschaft FC, Wirtz Fans).'),
            $this->message(43, 43, 'Marc', false, 'Wirtz qui égalise, classique.'),
            $this->message(55, 44, $partnerName, false, 'On tient le Miroir, faut que ça tienne…'),
            $this->goal(58, $homeLabel, 'A. Griezmann', 'home', '2-1'),
            $this->message(59, 46, 'Vous', true, 'YES ! Griezmann décisif, on tient le 2-1.'),
        ];

        if ('termine' === $state) {
            $items[] = $this->goal(88, $awayLabel, 'N. Füllkrug', 'away', '2-2');
            $items[] = $this->auto(88, 88, 'N. Füllkrug marque ! 2 joueurs ont ce buteur (1 équipe : Underdogs United).');
            $items[] = $this->message(89, 89, 'Marc', false, 'Füllkrug au bout du suspense…');
            $items[] = $this->system(90, 'Fin du match — 2-2');
        } elseif ('live' === $state) {
            $items[] = $this->message(67, 47, $partnerName, false, 'On encaisse plus rien jusqu’à la fin !');
        }

        return $this->sortFeed($items);
    }

    /**
     * Fil à partir des données réelles du match.
     *
     * @param list<array{name: string, minute: ?int, side: string, photo: ?string, buteur_id?: int}> $goals
     * @param list<Pronostic>                                                                              $pronostics
     *
     * @return list<array<string, mixed>>
     */
    public function buildMatchFeed(
        GameMatch $match,
        array $goals,
        array $pronostics,
        string $homeLabel,
        string $awayLabel,
        bool $isUpcoming,
        bool $isFinished = false,
    ): array {
        $items = [
            $this->message(null, 0, 'Organisateur', false, 'Bon match à tous — pronos verrouillés au coup d’envoi.'),
        ];

        $sort = 1;
        foreach ($this->insightsService->buildPreKickoffInsightTexts($match, $pronostics) as $text) {
            $items[] = $this->auto(null, $sort++, $text);
        }

        if ($isUpcoming) {
            return $this->sortFeed($items);
        }

        $items[] = $this->system($sort++, 'Coup d\'envoi');

        $runningHome = 0;
        $runningAway = 0;
        $sortedGoals = $goals;
        usort($sortedGoals, static fn (array $a, array $b): int => ($a['minute'] ?? 0) <=> ($b['minute'] ?? 0));

        foreach ($sortedGoals as $goal) {
            $side = $goal['side'] ?? 'home';
            if ('home' === $side) {
                ++$runningHome;
            } else {
                ++$runningAway;
            }
            $minute = (int) ($goal['minute'] ?? 0);
            $teamLabel = 'home' === $side ? $homeLabel : $awayLabel;
            $scoreAfter = sprintf('%d-%d', $runningHome, $runningAway);

            $items[] = $this->goal($minute, $teamLabel, (string) $goal['name'], $side, $scoreAfter);

            $buteurInsight = $this->insightsService->buildButeurGoalInsightText($goal);
            if (null !== $buteurInsight) {
                $items[] = $this->auto($minute, $minute * 10 + 1, $buteurInsight);
            }
        }

        if ($isFinished && null !== $match->getScoreDomicile() && null !== $match->getScoreExterieur()) {
            $items[] = $this->system(9999, sprintf(
                'Fin du match — %d-%d',
                $match->getScoreDomicile(),
                $match->getScoreExterieur(),
            ));
        }

        return $this->sortFeed($items);
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return list<array<string, mixed>>
     */
    private function sortFeed(array $items): array
    {
        usort($items, static function (array $a, array $b): int {
            $minuteA = $a['minute'] ?? -1;
            $minuteB = $b['minute'] ?? -1;
            if ($minuteA !== $minuteB) {
                return $minuteA <=> $minuteB;
            }

            return ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0);
        });

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function goal(int $minute, string $teamLabel, string $scorer, string $side, string $scoreAfter): array
    {
        return [
            'type' => 'goal',
            'minute' => $minute,
            'sort' => $minute * 10,
            'team_label' => $teamLabel,
            'scorer' => $scorer,
            'side' => $side,
            'score_after' => $scoreAfter,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function message(?int $minute, int $sort, string $author, bool $self, string $text): array
    {
        return [
            'type' => 'message',
            'minute' => $minute,
            'sort' => $sort,
            'author' => $author,
            'self' => $self,
            'text' => $text,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function auto(?int $minute, int $sort, string $text): array
    {
        return [
            'type' => 'auto',
            'minute' => $minute,
            'sort' => $sort,
            'author' => self::AUTO_AUTHOR,
            'text' => $text,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function system(int $sort, string $text): array
    {
        return [
            'type' => 'system',
            'minute' => null,
            'sort' => $sort,
            'text' => $text,
        ];
    }
}
