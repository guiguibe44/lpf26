<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Country;
use App\Entity\GameMatch;
use App\Entity\Joker;
use App\Entity\Team;
use App\Entity\TeamJokerUsage;
use App\Entity\User;
use App\Repository\CountryRepository;
use App\Repository\GameMatchRepository;
use App\Repository\JokerRepository;
use App\Repository\TeamJokerUsageRepository;
use App\Repository\TeamMemberRepository;
use App\Repository\TeamRepository;
use Doctrine\ORM\EntityManagerInterface;

final class TeamJokerService
{
    public function __construct(
        private readonly JokerRepository $jokerRepository,
        private readonly TeamJokerUsageRepository $teamJokerUsageRepository,
        private readonly TeamMemberRepository $teamMemberRepository,
        private readonly MatchStatusResolver $matchStatusResolver,
        private readonly PronosticScoringService $pronosticScoringService,
        private readonly TeamRepository $teamRepository,
        private readonly MatchEspionService $matchEspionService,
        private readonly ButeurJokerPointsService $buteurJokerPointsService,
        private readonly TeamRankingService $teamRankingService,
        private readonly JokerDefenseService $jokerDefenseService,
        private readonly GameMatchRepository $gameMatchRepository,
        private readonly CountryRepository $countryRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly CompetitionStatus $competitionStatus,
    ) {
    }

    /**
     * @return list<array{
     *     joker: Joker,
     *     usage: TeamJokerUsage|null,
     *     status: string,
     *     status_label: string
     * }>
     */
    public function buildOverviewForTeam(Team $team): array
    {
        $usagesByJokerId = [];
        foreach ($this->teamJokerUsageRepository->findByTeamOrdered($team) as $usage) {
            $jokerId = $usage->getJoker()?->getId();
            if (null !== $jokerId) {
                $usagesByJokerId[(int) $jokerId] = $usage;
            }
        }

        $rows = [];
        foreach ($this->jokerRepository->findAllOrdered() as $joker) {
            $jokerId = (int) $joker->getId();
            $usage = $usagesByJokerId[$jokerId] ?? null;

            $favoriteCountryName = null;
            if ($usage instanceof TeamJokerUsage) {
                $status = 'used';
                $statusLabel = 'Utilisé';
            } elseif (Joker::CODE_EQUIPE_FAVORITE === $joker->getCode() && $team->getFavoriteCountry() instanceof Country) {
                $status = 'used';
                $statusLabel = 'Utilisé';
                $favoriteCountryName = (string) $team->getFavoriteCountry()->getNom();
            } elseif (!$joker->isActive()) {
                $status = 'inactive';
                $statusLabel = 'Indisponible';
            } else {
                $status = 'available';
                $statusLabel = 'Disponible';
            }

            $rows[] = [
                'joker' => $joker,
                'usage' => $usage,
                'status' => $status,
                'status_label' => $statusLabel,
                'favorite_country_name' => $favoriteCountryName,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{name: string, image: ?string, code: string}>
     */
    public function buildUsageSummaryByMatchIdForTeam(Team $team): array
    {
        $map = [];
        foreach ($this->teamJokerUsageRepository->findByTeamOrdered($team) as $usage) {
            $matchId = $usage->getMatch()?->getId();
            $joker = $usage->getJoker();
            if (null === $matchId || !$joker instanceof Joker) {
                continue;
            }

            $target = $usage->getTargetTeam();
            $match = $usage->getMatch();
            $neutralized = $match instanceof GameMatch && $this->jokerDefenseService->isUsageNeutralized($usage);
            $map[(int) $matchId] = [
                'name' => $joker->getDisplayTitle(),
                'image' => $joker->getImage(),
                'code' => (string) $joker->getCode(),
                'target_team_name' => $target instanceof Team ? (string) $target->getName() : null,
                'effect_blocked' => $neutralized,
                'can_remove' => $match instanceof GameMatch && $this->canRemoveUsage($usage, $match),
            ];
        }

        return $map;
    }

    /**
     * @return array{
     *     can_manage: bool,
     *     reason: ?string,
     *     active_on_match: ?array{id: int, name: string, image: ?string, code: string},
     *     planned_on_other_matches: list<array{match_id: int, match_label: string, joker_name: string}>,
     *     jokers: list<array{
     *         id: int,
     *         code: string,
     *         name: string,
     *         description: ?string,
     *         image: ?string,
     *         can_play: bool,
     *         disabled_reason: ?string,
     *         already_used: bool
     *     }>
     * }
     */
    public function buildMatchPickerState(User $user, GameMatch $match): array
    {
        $team = $this->teamMemberRepository->findOneBy(['player' => $user])?->getTeam();
        if (!$team instanceof Team) {
            return [
                'can_manage' => false,
                'reason' => 'Rejoignez une équipe pour utiliser les jokers.',
                'active_on_match' => null,
                'planned_on_other_matches' => [],
                'jokers' => [],
                'opponent_teams' => [],
            ];
        }

        if (!$user->isCotisationPayee()) {
            return [
                'can_manage' => false,
                'reason' => 'Réglez votre cotisation pour utiliser les jokers.',
                'active_on_match' => null,
                'planned_on_other_matches' => [],
                'jokers' => [],
                'opponent_teams' => [],
            ];
        }

        $usageOnMatch = $this->teamJokerUsageRepository->findOneByTeamAndMatch($team, $match);
        $activeOnMatch = null;
        if ($usageOnMatch instanceof TeamJokerUsage) {
            $joker = $usageOnMatch->getJoker();
            $target = $usageOnMatch->getTargetTeam();
            $neutralized = $this->jokerDefenseService->isUsageNeutralized($usageOnMatch);
            $favoriteOnUsage = $team->getFavoriteCountry();
            $activeOnMatch = [
                'id' => (int) $joker?->getId(),
                'name' => $joker?->getDisplayTitle() ?? '',
                'description' => $joker?->getDescription(),
                'image' => $joker?->getImage(),
                'code' => (string) $joker?->getCode(),
                'target_team_id' => $target?->getId(),
                'target_team_name' => $target instanceof Team ? (string) $target->getName() : null,
                'favorite_country_name' => Joker::CODE_EQUIPE_FAVORITE === $joker?->getCode() && $favoriteOnUsage instanceof Country
                    ? (string) $favoriteOnUsage->getNom()
                    : null,
                'can_remove' => $this->canRemoveUsage($usageOnMatch, $match),
                'effect_blocked' => $neutralized,
            ];
        }

        $plannedOnOtherMatches = $this->buildPlannedOnOtherMatches($team, $match);

        $canPlaceOnThisMatch = $this->canPlaceOnMatch($team, $match);
        $usagesByJokerId = [];
        foreach ($this->teamJokerUsageRepository->findByTeamOrdered($team) as $usage) {
            $jid = $usage->getJoker()?->getId();
            if (null !== $jid) {
                $usagesByJokerId[(int) $jid] = $usage;
            }
        }

        $jokers = [];
        foreach ($this->jokerRepository->findAllOrdered() as $joker) {
            if (!$joker->isActive()) {
                continue;
            }

            $jokerId = (int) $joker->getId();
            $alreadyUsed = isset($usagesByJokerId[$jokerId]);
            $canPlay = false;
            $disabledReason = null;

            if ($activeOnMatch !== null) {
                $disabledReason = 'Un joker est déjà posé sur ce match.';
            } elseif ($alreadyUsed) {
                $disabledReason = 'Ce joker a déjà été utilisé par votre équipe.';
            } elseif (!$canPlaceOnThisMatch['allowed']) {
                $disabledReason = $canPlaceOnThisMatch['reason'];
            } elseif (Joker::CODE_DOUBLE_BUTEUR === $joker->getCode() && !$this->buteurJokerPointsService->isMatchEligibleForDoubleButeurJoker($team, $match)) {
                $disabledReason = $this->formatDoubleButeurIneligibleReason($team);
            } elseif (Joker::CODE_INVERSE_BUTEUR === $joker->getCode() && !$this->hasOpponentEligibleForInvertButeurOnMatch($team, $match)) {
                $disabledReason = 'Aucune équipe adverse n\'a un buteur dont le pays joue ce match.';
            } elseif (Joker::CODE_EQUIPE_FAVORITE === $joker->getCode()) {
                if ($team->getFavoriteCountry() instanceof Country) {
                    $disabledReason = 'Équipe favorite déjà enregistrée (Mon compte → Mon équipe).';
                } elseif ($this->competitionStatus->isStarted()) {
                    $disabledReason = 'Le choix de l\'équipe favorite n\'est plus possible.';
                } else {
                    $disabledReason = 'Enregistrez votre sélection dans Mon compte → Mon équipe.';
                }
            } else {
                $canPlay = true;
            }

            if (Joker::CODE_EQUIPE_FAVORITE === $joker->getCode() && $team->getFavoriteCountry() instanceof Country) {
                $alreadyUsed = true;
            }

            $isEspion = Joker::CODE_ESPION === $joker->getCode();

            $jokers[] = [
                'id' => $jokerId,
                'code' => (string) $joker->getCode(),
                'name' => $joker->getDisplayTitle(),
                'description' => $joker->getDescription(),
                'image' => $joker->getImage(),
                'requires_target_team' => \in_array($joker->getCode(), [Joker::CODE_PIQUE_POINTS, Joker::CODE_INVERSE_BUTEUR, Joker::CODE_INVERSE_SCORE], true),
                'requires_favorite_country' => false,
                'requires_confirmation' => $isEspion,
                'confirmation_message' => $isEspion ? Joker::ESPION_PLACE_CONFIRMATION : null,
                'can_play' => $canPlay,
                'disabled_reason' => $disabledReason,
                'already_used' => $alreadyUsed,
            ];
        }

        $opponentTeams = [];
        foreach ($this->teamRepository->findAll() as $opponent) {
            $opponentId = $opponent->getId();
            if (null === $opponentId || (int) $opponentId === (int) $team->getId()) {
                continue;
            }

            $opponentTeams[] = [
                'id' => (int) $opponentId,
                'name' => (string) $opponent->getName(),
                'buteur_countries' => $this->buteurJokerPointsService->getButeurCountryNamesForTeam($opponent),
                'match_eligible_inverse_buteur' => $this->buteurJokerPointsService->isMatchEligibleForTeamButeurCountries($opponent, $match),
                'shield_protected' => $this->jokerDefenseService->teamHasBouclierOnMatchday($opponent, $match),
            ];
        }

        $favoriteCountries = [];
        foreach ($this->countryRepository->findAllOrderedByName() as $country) {
            $countryId = $country->getId();
            if (null === $countryId) {
                continue;
            }

            $favoriteCountries[] = [
                'id' => (int) $countryId,
                'name' => (string) $country->getNom(),
            ];
        }

        usort(
            $opponentTeams,
            static fn (array $a, array $b): int => strcmp($a['name'], $b['name']),
        );

        $espionIntel = null;
        if (
            $activeOnMatch !== null
            && Joker::CODE_ESPION === ($activeOnMatch['code'] ?? '')
            && $this->matchEspionService->teamHasEspionOnMatchBeforeKickoff($team, $match)
        ) {
            $espionIntel = $this->matchEspionService->buildIntelForMatch($match);
        }

        $buteurCountries = $this->buteurJokerPointsService->getButeurCountryNamesForTeam($team);
        $teamShieldActive = $this->jokerDefenseService->teamHasBouclierOnMatchday($team, $match);
        $favorite = $team->getFavoriteCountry();
        $favoriteProtectionOnMatch = $this->jokerDefenseService->isTeamProtectedByFavoriteOnGroupMatch($team, $match);

        return [
            'can_manage' => true,
            'reason' => null,
            'active_on_match' => $activeOnMatch,
            'planned_on_other_matches' => $plannedOnOtherMatches,
            'jokers' => $jokers,
            'opponent_teams' => $opponentTeams,
            'espion_intel' => $espionIntel,
            'team_buteur_countries' => $buteurCountries,
            'team_shield_active' => $teamShieldActive,
            'matchday_label' => $this->formatMatchdayLabel($match),
            'favorite_countries' => $favoriteCountries,
            'team_favorite_country_name' => $favorite instanceof Country ? (string) $favorite->getNom() : null,
            'team_favorite_protection_on_match' => $favoriteProtectionOnMatch,
        ];
    }

    /**
     * @return array{allowed: bool, reason: ?string}
     */
    public function canPlaceOnMatch(Team $team, GameMatch $match): array
    {
        if (!$this->isMatchOpenForJoker($match)) {
            return [
                'allowed' => false,
                'reason' => 'Les jokers ne peuvent être posés que sur un match à venir (avant le coup d\'envoi).',
            ];
        }

        if ($this->teamJokerUsageRepository->findOneByTeamAndMatch($team, $match) instanceof TeamJokerUsage) {
            return [
                'allowed' => false,
                'reason' => 'Votre équipe a déjà un joker sur ce match.',
            ];
        }

        return ['allowed' => true, 'reason' => null];
    }

    public function placeJoker(User $user, GameMatch $match, Joker $joker, ?Team $targetTeam = null, ?Country $favoriteCountry = null): void
    {
        $teamMember = $this->teamMemberRepository->findOneBy(['player' => $user]);
        $team = $teamMember?->getTeam();
        if (!$team instanceof Team) {
            throw new \InvalidArgumentException('Vous devez faire partie d\'une équipe.');
        }

        if (!$user->isCotisationPayee()) {
            throw new \InvalidArgumentException('Réglez votre cotisation pour utiliser les jokers.');
        }

        if (!$joker->isActive()) {
            throw new \InvalidArgumentException('Ce joker n\'est pas disponible.');
        }

        $canPlace = $this->canPlaceOnMatch($team, $match);
        if (!$canPlace['allowed']) {
            throw new \InvalidArgumentException((string) $canPlace['reason']);
        }

        if (Joker::CODE_EQUIPE_FAVORITE === $joker->getCode()) {
            throw new \InvalidArgumentException('Enregistrez votre équipe favorite dans Mon compte → Mon équipe (avant le début de la compétition).');
        }

        if ($this->teamJokerUsageRepository->findOneByTeamAndJoker($team, $joker) instanceof TeamJokerUsage) {
            throw new \InvalidArgumentException('Votre équipe a déjà utilisé ce joker.');
        }

        if (Joker::CODE_DOUBLE_BUTEUR === $joker->getCode() && !$this->buteurJokerPointsService->isMatchEligibleForDoubleButeurJoker($team, $match)) {
            throw new \InvalidArgumentException($this->formatDoubleButeurIneligibleReason($team));
        }

        $targetTeam = $this->resolveTargetTeamForJoker($team, $joker, $match, $targetTeam);
        $favoriteCountry = $this->resolveFavoriteCountryForJoker($team, $joker, $favoriteCountry);

        if ($favoriteCountry instanceof Country) {
            $team->setFavoriteCountry($favoriteCountry);
            $this->entityManager->persist($team);
        }

        $usage = (new TeamJokerUsage())
            ->setTeam($team)
            ->setJoker($joker)
            ->setMatch($match)
            ->setTargetTeam($targetTeam);

        $this->entityManager->persist($usage);
        $this->entityManager->flush();

        $this->afterJokerUsageChanged($match, $joker);

        if (null !== $match->getScoreDomicile() && null !== $match->getScoreExterieur()) {
            $this->pronosticScoringService->rescoreForMatch($match);
        }
    }

    public function removeJokerFromMatch(User $user, GameMatch $match): void
    {
        $teamMember = $this->teamMemberRepository->findOneBy(['player' => $user]);
        $team = $teamMember?->getTeam();
        if (!$team instanceof Team) {
            throw new \InvalidArgumentException('Vous devez faire partie d\'une équipe.');
        }

        if (!$user->isCotisationPayee()) {
            throw new \InvalidArgumentException('Réglez votre cotisation pour utiliser les jokers.');
        }

        if (!$this->isMatchOpenForJoker($match)) {
            throw new \InvalidArgumentException('Ce joker ne peut plus être retiré (match déjà commencé ou terminé).');
        }

        $usage = $this->teamJokerUsageRepository->findOneByTeamAndMatch($team, $match);
        if (!$usage instanceof TeamJokerUsage) {
            throw new \InvalidArgumentException('Aucun joker posé sur ce match.');
        }

        if (!$this->canRemoveUsage($usage, $match)) {
            throw new \InvalidArgumentException('Ce joker ne peut pas être retiré une fois posé.');
        }

        $joker = $usage->getJoker();

        if (Joker::CODE_EQUIPE_FAVORITE === $joker?->getCode()) {
            $team->setFavoriteCountry(null);
            $this->entityManager->persist($team);
        }

        $this->entityManager->remove($usage);
        $this->entityManager->flush();

        if ($joker instanceof Joker) {
            $this->afterJokerUsageChanged($match, $joker);
        }

        if (null !== $match->getScoreDomicile() && null !== $match->getScoreExterieur()) {
            $this->pronosticScoringService->rescoreForMatch($match);
        }
    }

    /**
     * @return array<int, array{name: string, image: ?string, code: string}>
     */
    public function buildActiveJokersByTeamIdForMatch(GameMatch $match): array
    {
        $map = [];
        foreach ($this->teamJokerUsageRepository->findByMatch($match) as $usage) {
            $teamId = $usage->getTeam()?->getId();
            $joker = $usage->getJoker();
            if (null === $teamId || !$joker instanceof Joker) {
                continue;
            }

            $target = $usage->getTargetTeam();
            $usageMatch = $usage->getMatch();
            $neutralized = $usageMatch instanceof GameMatch && $this->jokerDefenseService->isUsageNeutralized($usage);
            $map[(int) $teamId] = [
                'name' => $joker->getDisplayTitle(),
                'image' => $joker->getImage(),
                'code' => (string) $joker->getCode(),
                'target_team_name' => $target instanceof Team ? (string) $target->getName() : null,
                'effect_blocked' => $neutralized,
            ];
        }

        return $map;
    }

    /**
     * Jokers déjà planifiés sur d'autres matchs à venir (informatif, ne bloque pas).
     *
     * @return list<array{match_id: int, match_label: string, joker_name: string}>
     */
    private function buildPlannedOnOtherMatches(Team $team, GameMatch $currentMatch): array
    {
        $currentMatchId = $currentMatch->getId();
        $planned = [];

        foreach ($this->teamJokerUsageRepository->findByTeamOrdered($team) as $usage) {
            $usageMatch = $usage->getMatch();
            if (!$usageMatch instanceof GameMatch) {
                continue;
            }

            $usageMatchId = $usageMatch->getId();
            if (null === $usageMatchId || (null !== $currentMatchId && (int) $usageMatchId === (int) $currentMatchId)) {
                continue;
            }

            if ($this->matchStatusResolver->isMatchFinished($usageMatch)) {
                continue;
            }

            $planned[] = [
                'match_id' => (int) $usageMatchId,
                'match_label' => $this->formatMatchLabel($usageMatch),
                'joker_name' => $usage->getJoker()?->getDisplayTitle() ?? '',
            ];
        }

        return $planned;
    }

    public function isMatchOpenForJoker(GameMatch $match): bool
    {
        return $this->matchStatusResolver->canEditBeforeKickoff($match);
    }

    public function canRemoveUsage(TeamJokerUsage $usage, GameMatch $match): bool
    {
        if (!$this->isMatchOpenForJoker($match)) {
            return false;
        }

        if (Joker::CODE_ESPION === $usage->getJoker()?->getCode()) {
            return false;
        }

        return true;
    }

    private function resolveTargetTeamForJoker(Team $team, Joker $joker, GameMatch $match, ?Team $targetTeam): ?Team
    {
        if (\in_array($joker->getCode(), [Joker::CODE_PIQUE_POINTS, Joker::CODE_INVERSE_BUTEUR, Joker::CODE_INVERSE_SCORE], true)) {
            if (!$targetTeam instanceof Team) {
                throw new \InvalidArgumentException('Choisissez l\'équipe adverse à cibler.');
            }

            $ownId = $team->getId();
            $targetId = $targetTeam->getId();
            if (null === $ownId || null === $targetId || (int) $ownId === (int) $targetId) {
                throw new \InvalidArgumentException('Vous ne pouvez pas cibler votre propre équipe.');
            }

            if (Joker::CODE_INVERSE_BUTEUR === $joker->getCode()
                && !$this->buteurJokerPointsService->isMatchEligibleForTeamButeurCountries($targetTeam, $match)) {
                throw new \InvalidArgumentException($this->formatInverseButeurIneligibleReason($targetTeam));
            }

            return $targetTeam;
        }

        if ($targetTeam instanceof Team) {
            throw new \InvalidArgumentException('Ce joker ne nécessite pas d\'équipe cible.');
        }

        return null;
    }

    private function resolveFavoriteCountryForJoker(Team $team, Joker $joker, ?Country $favoriteCountry): ?Country
    {
        if (Joker::CODE_EQUIPE_FAVORITE !== $joker->getCode()) {
            if ($favoriteCountry instanceof Country) {
                throw new \InvalidArgumentException('Ce joker ne nécessite pas de pays favori.');
            }

            return null;
        }

        if (!$favoriteCountry instanceof Country) {
            throw new \InvalidArgumentException('Choisissez votre équipe favorite (sélection nationale).');
        }

        if (null === $favoriteCountry->getId()) {
            throw new \InvalidArgumentException('Pays invalide.');
        }

        return $favoriteCountry;
    }

    private function afterJokerUsageChanged(GameMatch $match, Joker $joker): void
    {
        if (Joker::CODE_BOUCLIER === $joker->getCode()) {
            $this->rescoreFinishedMatchesOnMatchday($match);

            return;
        }

        if (Joker::CODE_EQUIPE_FAVORITE === $joker->getCode()) {
            if (null !== $match->getScoreDomicile() && null !== $match->getScoreExterieur()) {
                $this->pronosticScoringService->rescoreForMatch($match);
            }

            return;
        }

        if (Joker::CODE_INVERSE_SCORE === $joker->getCode()
            || Joker::CODE_PIQUE_POINTS === $joker->getCode()
            || Joker::CODE_INVERSE_BUTEUR === $joker->getCode()
            || Joker::CODE_COLLECTE_POINTS === $joker->getCode()) {
            if (null !== $match->getScoreDomicile() && null !== $match->getScoreExterieur()) {
                $this->pronosticScoringService->rescoreForMatch($match);
            }

            if (Joker::CODE_INVERSE_BUTEUR === $joker->getCode()) {
                $this->teamRankingService->rebuildSnapshotsFromMatch($match);
            }

            return;
        }

        if (Joker::CODE_DOUBLE_BUTEUR === $joker->getCode()) {
            $this->teamRankingService->rebuildSnapshotsFromMatch($match);
        }
    }

    private function rescoreFinishedMatchesOnMatchday(GameMatch $anchor): void
    {
        $dayKey = MatchdayKey::fromMatch($anchor);
        if (null === $dayKey) {
            return;
        }

        foreach ($this->gameMatchRepository->findByCalendarDay($dayKey) as $dayMatch) {
            if (null !== $dayMatch->getScoreDomicile() && null !== $dayMatch->getScoreExterieur()) {
                $this->pronosticScoringService->rescoreForMatch($dayMatch);
            }
        }
    }

    public function buildPlacementSuccessMessage(Joker $joker, ?Team $targetTeam, GameMatch $match, ?Country $favoriteCountry = null): string
    {
        $message = sprintf('Joker « %s » posé sur ce match.', $joker->getDisplayTitle());
        if ($targetTeam instanceof Team) {
            $message = sprintf(
                'Joker « %s » posé : cible %s.',
                $joker->getDisplayTitle(),
                (string) $targetTeam->getName(),
            );
            if ($this->jokerDefenseService->wouldOffensiveJokerBeNeutralized($targetTeam, $match, $joker)) {
                $message .= ' La cible est protégée sur ce match : votre joker est consommé sans effet sur elle.';
            }
        }

        if (Joker::CODE_EQUIPE_FAVORITE === $joker->getCode() && $favoriteCountry instanceof Country) {
            $message = sprintf(
                'Équipe favorite enregistrée : %s. Ce choix reste secret. Protection sur les matchs de poule où cette sélection joue.',
                (string) $favoriteCountry->getNom(),
            );
        }

        if (Joker::CODE_BOUCLIER === $joker->getCode()) {
            $dayLabel = $this->formatMatchdayLabel($match);
            $message = sprintf(
                'Joker « %s » actif : votre équipe est protégée pour la journée%s contre les jokers adverses qui vous ciblent.',
                $joker->getDisplayTitle(),
                null !== $dayLabel ? ' du '.$dayLabel : '',
            );
        }

        return $message;
    }

    private function formatMatchdayLabel(GameMatch $match): ?string
    {
        $dateHeure = $match->getDateHeure();
        if (!$dateHeure instanceof \DateTimeImmutable) {
            return null;
        }

        $formatter = new \IntlDateFormatter(
            'fr_FR',
            \IntlDateFormatter::LONG,
            \IntlDateFormatter::NONE,
            $dateHeure->getTimezone(),
        );

        return $formatter->format($dateHeure) ?: $dateHeure->format('d/m/Y');
    }

    private function hasOpponentEligibleForInvertButeurOnMatch(Team $team, GameMatch $match): bool
    {
        $ownId = $team->getId();
        foreach ($this->teamRepository->findAll() as $opponent) {
            $opponentId = $opponent->getId();
            if (null === $ownId || null === $opponentId || (int) $ownId === (int) $opponentId) {
                continue;
            }

            if ($this->buteurJokerPointsService->isMatchEligibleForTeamButeurCountries($opponent, $match)) {
                return true;
            }
        }

        return false;
    }

    private function formatInverseButeurIneligibleReason(Team $targetTeam): string
    {
        $countries = $this->buteurJokerPointsService->getButeurCountryNamesForTeam($targetTeam);
        if ([] === $countries) {
            return sprintf(
                'L\'équipe %s n\'a pas de buteurs avec pays défini.',
                (string) $targetTeam->getName(),
            );
        }

        return sprintf(
            'Ce joker ne peut être posé que sur un match impliquant le pays d\'un buteur de %s (%s).',
            (string) $targetTeam->getName(),
            implode(', ', $countries),
        );
    }

    private function formatDoubleButeurIneligibleReason(Team $team): string
    {
        $countries = $this->buteurJokerPointsService->getButeurCountryNamesForTeam($team);
        if ([] === $countries) {
            return 'Choisissez d\'abord les buteurs de votre équipe (pays requis).';
        }

        return sprintf(
            'Ce joker ne peut être posé que sur un match impliquant le pays d\'un de vos buteurs (%s).',
            implode(', ', $countries),
        );
    }

    private function formatMatchLabel(?GameMatch $match): string
    {
        if (!$match instanceof GameMatch) {
            return 'un autre match';
        }

        $home = $match->getPaysDomicile()?->getNom() ?? '?';
        $away = $match->getPaysExterieur()?->getNom() ?? '?';

        return sprintf('%s — %s', $home, $away);
    }
}
