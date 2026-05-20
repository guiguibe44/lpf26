<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Country;
use App\Entity\GameMatch;
use App\Entity\Joker;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\JokerRepository;

final class TeamFavoriteCountryService
{
    public function __construct(
        private readonly CompetitionStatus $competitionStatus,
        private readonly JokerRepository $jokerRepository,
    ) {
    }

    public function canManageFavoriteCountry(?User $user): bool
    {
        if (!$user instanceof User || !$user->isCotisationPayee()) {
            return false;
        }

        return !$this->competitionStatus->isStarted();
    }

    public function assertCanManageFavoriteCountry(User $user): void
    {
        if (!$user->isCotisationPayee()) {
            throw new \InvalidArgumentException('Réglez votre cotisation pour choisir votre équipe favorite.');
        }

        if ($this->competitionStatus->isStarted()) {
            throw new \InvalidArgumentException('Le choix de l\'équipe favorite est verrouillé depuis le début de la compétition.');
        }
    }

    public function setFavoriteCountry(Team $team, ?Country $country): void
    {
        $team->setFavoriteCountry($country);
    }

    /**
     * @return array{
     *     can_manage: bool,
     *     locked: bool,
     *     country_name: ?string,
     *     lock_reason: ?string
     * }
     */
    public function buildAccountState(Team $team, User $user): array
    {
        $favorite = $team->getFavoriteCountry();
        $locked = $this->competitionStatus->isStarted();
        $canManage = $this->canManageFavoriteCountry($user);

        $lockReason = null;
        if ($locked) {
            $startAt = $this->competitionStatus->getStartAt();
            $lockReason = null !== $startAt
                ? sprintf('Verrouillé depuis le %s.', $startAt->format('d/m/Y H:i'))
                : 'Verrouillé depuis le début de la compétition.';
        } elseif (!$user->isCotisationPayee()) {
            $lockReason = 'Réglez votre cotisation pour enregistrer ce choix.';
        }

        return [
            'can_manage' => $canManage,
            'locked' => $locked,
            'country_name' => $favorite instanceof Country ? (string) $favorite->getNom() : null,
            'lock_reason' => $lockReason,
        ];
    }

    /**
     * Contexte d’affichage des cartes match (poules) où joue l’équipe favorite de l’équipe.
     *
     * @param iterable<GameMatch> $matches
     *
     * @return array{
     *     country_id: int,
     *     country_name: string,
     *     joker_name: string,
     *     joker_image: ?string,
     *     match_ids: array<int, true>
     * }|null
     */
    public function buildMatchCardHighlight(Team $team, iterable $matches): ?array
    {
        $favorite = $team->getFavoriteCountry();
        if (!$favorite instanceof Country || null === $favorite->getId()) {
            return null;
        }

        $favoriteId = (int) $favorite->getId();
        $matchIds = [];
        foreach ($matches as $match) {
            if (!$match instanceof GameMatch) {
                continue;
            }

            if ($this->isMatchHighlightedForFavoriteCountry($match, $favoriteId)) {
                $matchId = $match->getId();
                if (null !== $matchId) {
                    $matchIds[(int) $matchId] = true;
                }
            }
        }

        $joker = null;
        foreach ($this->jokerRepository->findAllOrdered() as $candidate) {
            if (Joker::CODE_EQUIPE_FAVORITE === $candidate->getCode()) {
                $joker = $candidate;

                break;
            }
        }

        return [
            'country_id' => $favoriteId,
            'country_name' => (string) $favorite->getNom(),
            'joker_name' => $joker instanceof Joker ? (string) $joker->getName() : 'Équipe favorite',
            'joker_image' => $joker?->getImage(),
            'match_ids' => $matchIds,
        ];
    }

    public function isMatchHighlightedForFavoriteCountry(GameMatch $match, int $favoriteCountryId): bool
    {
        if ($favoriteCountryId <= 0) {
            return false;
        }

        if (!$match->isGroupStageMatch() && !$match->isKdoMatch()) {
            return false;
        }

        foreach ([$match->getPaysDomicile(), $match->getPaysExterieur()] as $country) {
            if ($country instanceof Country && (int) $country->getId() === $favoriteCountryId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{country_id?: int}|null $highlight
     */
    public function isMatchHighlighted(?array $highlight, GameMatch $match): bool
    {
        if (null === $highlight || !isset($highlight['country_id'])) {
            return false;
        }

        return $this->isMatchHighlightedForFavoriteCountry($match, (int) $highlight['country_id']);
    }
}
