<?php

declare(strict_types=1);

namespace App\Service;

use App\Data\JokerTestScenarioDefinition;
use App\Entity\GameMatch;
use App\Entity\Team;
use App\Entity\TeamJokerUsage;
use App\Repository\GameMatchRepository;
use App\Repository\PronosticRepository;
use App\Repository\TeamJokerUsageRepository;
use App\Repository\TeamMemberRepository;
use App\Repository\TeamRankingSnapshotRepository;
use App\Repository\TeamRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class JokerTestScenarioDashboardBuilder
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly JokerTestScenarioStateStore $stateStore,
        private readonly GameMatchRepository $gameMatchRepository,
        private readonly TeamRepository $teamRepository,
        private readonly TeamMemberRepository $teamMemberRepository,
        private readonly PronosticRepository $pronosticRepository,
        private readonly TeamJokerUsageRepository $teamJokerUsageRepository,
        private readonly TeamRankingSnapshotRepository $teamRankingSnapshotRepository,
        private readonly JokerDefenseService $jokerDefenseService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $state = $this->stateStore->read();
        $steps = JokerTestScenarioDefinition::steps();

        if (null === $state) {
            return [
                'initialized' => false,
                'steps' => $steps,
                'message' => 'Scénario non initialisé. Lancez : php bin/console app:joker-test:setup',
            ];
        }

        $stepIndex = $state['step_index'];
        $currentStep = $steps[$stepIndex] ?? $steps[array_key_last($steps)];
        $nextStep = $steps[$stepIndex + 1] ?? null;

        $teams = [];
        foreach ($state['team_ids'] as $key => $teamId) {
            $team = $this->teamRepository->find($teamId);
            if ($team instanceof Team) {
                $teams[$key] = $this->buildTeamRow($key, $team);
            }
        }

        $matches = [];
        foreach ($state['match_ids'] as $index => $matchId) {
            $match = $this->gameMatchRepository->find($matchId);
            if ($match instanceof GameMatch) {
                $matches[$index] = $this->buildMatchRow($match, $state['team_ids']);
            }
        }

        return [
            'initialized' => true,
            'seeded_at' => $state['seeded_at'],
            'step_index' => $stepIndex,
            'step_total' => \count($steps),
            'current_step' => $currentStep,
            'next_step' => $nextStep,
            'can_advance' => $stepIndex < \count($steps) - 1,
            'steps' => $steps,
            'teams' => $teams,
            'matches' => $matches,
            'accounts' => $this->buildAccountsHint(),
            'joker_plan' => JokerTestScenarioDefinition::JOKER_PLACEMENTS,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTeamRow(string $key, Team $team): array
    {
        $favorite = $team->getFavoriteCountry();

        return [
            'key' => $key,
            'id' => (int) $team->getId(),
            'name' => (string) $team->getName(),
            'favorite_country' => $favorite?->getNom(),
            'members' => array_map(
                static fn ($m) => [
                    'nickname' => (string) $m->getNickname(),
                    'email' => (string) $m->getPlayer()?->getEmail(),
                    'buteur' => trim(((string) $m->getPlayer()?->getButeurChoisi()?->getPrenom()).' '.((string) $m->getPlayer()?->getButeurChoisi()?->getNom())),
                ],
                $team->getMembers()->toArray(),
            ),
        ];
    }

    /**
     * @param array<string, int> $teamIdsByKey
     *
     * @return array<string, mixed>
     */
    private function buildMatchRow(GameMatch $match, array $teamIdsByKey): array
    {
        $home = (string) $match->getPaysDomicile()?->getNom();
        $away = (string) $match->getPaysExterieur()?->getNom();
        $kickoff = $match->getDateHeure();

        $jokers = [];
        foreach ($this->teamJokerUsageRepository->findByMatch($match) as $usage) {
            $jokers[] = $this->formatJokerUsage($usage, $match);
        }

        $playerTeamMap = $this->teamMemberRepository->findPlayerTeamMap();
        $pronostics = [];
        foreach ($this->pronosticRepository->findByMatchWithTeamMembers($match) as $pronostic) {
            $playerId = (int) $pronostic->getJoueur()?->getId();
            $teamId = $playerTeamMap[$playerId] ?? 0;
            $teamKey = array_search($teamId, $teamIdsByKey, true);

            $pronostics[] = [
                'team' => false !== $teamKey ? (string) $teamKey : '?',
                'email' => (string) $pronostic->getJoueur()?->getEmail(),
                'prono' => sprintf('%d-%d', $pronostic->getScoreDomicile(), $pronostic->getScoreExterieur()),
                'points' => $pronostic->getPoints(),
                'points_equipe' => $pronostic->getPointsEquipe() ?? $pronostic->getPoints(),
            ];
        }

        usort($pronostics, static fn (array $a, array $b): int => strcmp($a['team'], $b['team']));

        $rankings = [];
        foreach ($this->teamRankingSnapshotRepository->findBy(['matchRef' => $match], ['position' => 'ASC']) as $snapshot) {
            $teamId = (int) $snapshot->getTeam()?->getId();
            $teamKey = array_search($teamId, $teamIdsByKey, true);
            $rankings[] = [
                'team' => false !== $teamKey ? (string) $teamKey : '?',
                'position' => $snapshot->getPosition(),
                'total_points' => $snapshot->getTotalPoints(),
            ];
        }

        return [
            'id' => (int) $match->getId(),
            'label' => sprintf('%s – %s', $home, $away),
            'kickoff' => $kickoff?->format('d/m/Y H:i'),
            'statut' => $match->getStatut(),
            'score' => null !== $match->getScoreDomicile() && null !== $match->getScoreExterieur()
                ? sprintf('%d-%d', $match->getScoreDomicile(), $match->getScoreExterieur())
                : '—',
            'jokers' => $jokers,
            'pronostics' => $pronostics,
            'rankings' => $rankings,
            'live_url' => $this->urlGenerator->generate('app_match_live', ['id' => (int) $match->getId()]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatJokerUsage(TeamJokerUsage $usage, GameMatch $match): array
    {
        $joker = $usage->getJoker();
        $code = (string) $joker?->getCode();
        $target = $usage->getTargetTeam();
        $neutralized = $this->jokerDefenseService->isUsageNeutralized($usage);

        return [
            'team' => (string) $usage->getTeam()?->getName(),
            'code' => $code,
            'name' => $joker?->getDisplayTitle() ?? $code,
            'target' => $target instanceof Team ? (string) $target->getName() : null,
            'effect_blocked' => $neutralized,
        ];
    }

    /**
     * @return list<array{email: string, password: string, team: string}>
     */
    private function buildAccountsHint(): array
    {
        $rows = [];
        foreach (JokerTestScenarioDefinition::TEAMS as $key => $teamData) {
            foreach ($teamData['players'] as $player) {
                $rows[] = [
                    'team' => $key,
                    'email' => $player['email'],
                    'password' => JokerTestScenarioDefinition::DEFAULT_PASSWORD,
                ];
            }
        }

        return $rows;
    }
}
