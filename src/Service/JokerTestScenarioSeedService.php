<?php

declare(strict_types=1);

namespace App\Service;

use App\Data\JokerTestScenarioDefinition;
use App\Entity\Buteur;
use App\Entity\Country;
use App\Entity\GameMatch;
use App\Entity\Joker;
use App\Entity\Pronostic;
use App\Entity\Team;
use App\Entity\TeamJokerUsage;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Repository\ButeurRepository;
use App\Repository\CountryRepository;
use App\Repository\JokerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class JokerTestScenarioSeedService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CountryRepository $countryRepository,
        private readonly ButeurRepository $buteurRepository,
        private readonly JokerRepository $jokerRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JokerTestScenarioStateStore $stateStore,
    ) {
    }

    /**
     * @return array{
     *     teams: array<string, int>,
     *     match_ids: list<int>,
     *     buteur_ids: array<string, int>
     * }
     */
    public function seed(): array
    {
        $tz = new \DateTimeZone(JokerTestScenarioDefinition::TIMEZONE);
        $day = new \DateTimeImmutable('tomorrow', $tz);

        $teams = [];
        $usersByTeam = [];

        foreach (JokerTestScenarioDefinition::TEAMS as $key => $teamData) {
            $team = (new Team())
                ->setName($teamData['name'])
                ->setSlogan($teamData['slogan']);

            if (isset($teamData['favorite_country'])) {
                $team->setFavoriteCountry($this->requireCountry((string) $teamData['favorite_country']));
            }

            $this->entityManager->persist($team);
            $teams[$key] = $team;
            $usersByTeam[$key] = [];

            foreach ($teamData['players'] as $playerData) {
                $user = new User();
                $user->setEmail($playerData['email']);
                $user->setPassword($this->passwordHasher->hashPassword($user, JokerTestScenarioDefinition::DEFAULT_PASSWORD));
                $user->setRoles(['ROLE_USER']);
                $user->setCotisationPayee(true);
                $user->setButeurChoisi($this->requireButeurForCountry($playerData['buteur_country']));
                $this->entityManager->persist($user);

                $member = (new TeamMember())
                    ->setTeam($team)
                    ->setPlayer($user)
                    ->setNickname($playerData['nickname']);
                $this->entityManager->persist($member);

                $usersByTeam[$key][] = $user;
            }
        }

        $this->entityManager->flush();

        $matchIds = [];
        $matches = [];

        foreach (JokerTestScenarioDefinition::MATCHES as $index => $matchData) {
            $home = $this->requireCountry($matchData['home']);
            $away = $this->requireCountry($matchData['away']);
            $kickoff = $day->setTime($matchData['kickoff_hour'], $matchData['kickoff_minute']);

            $match = (new GameMatch())
                ->setPaysDomicile($home)
                ->setPaysExterieur($away)
                ->setDateHeure($kickoff)
                ->setPhase($matchData['group_phase'])
                ->setVenueName(JokerTestScenarioDefinition::MATCH_MARKER)
                ->setStatut('SCHEDULED')
                ->setApiFootballSyncEnabled(false)
                ->setApiFootballFixtureId(null)
                ->setPointsScoreExact(3)
                ->setPointsBonResultat(1)
                ->setPointsMauvaisResultat(0);

            $this->entityManager->persist($match);
            $matches[$index] = $match;
        }

        $this->entityManager->flush();

        foreach ($matches as $match) {
            $id = $match->getId();
            if (null !== $id) {
                $matchIds[] = (int) $id;
            }
        }

        foreach (JokerTestScenarioDefinition::PRONOSTICS_BY_TEAM as $teamKey => $pronosByMatch) {
            $team = $teams[$teamKey];
            foreach ($usersByTeam[$teamKey] as $userIndex => $user) {
                foreach ($pronosByMatch as $matchIndex => [$homeScore, $awayScore]) {
                    $match = $matches[$matchIndex];
                    $offset = $userIndex % 2;
                    $pronostic = (new Pronostic())
                        ->setJoueur($user)
                        ->setMatch($match)
                        ->setScoreDomicile($homeScore + $offset)
                        ->setScoreExterieur($awayScore);
                    $this->entityManager->persist($pronostic);
                }
            }
        }

        $jokersByCode = [];
        foreach ($this->jokerRepository->findAllOrdered() as $joker) {
            $code = $joker->getCode();
            if (null !== $code) {
                $jokersByCode[$code] = $joker;
            }
        }

        foreach (JokerTestScenarioDefinition::JOKER_PLACEMENTS as $placement) {
            $team = $teams[$placement['team']];
            $match = $matches[$placement['match_index']];
            $joker = $this->requireJoker($jokersByCode, $placement['joker']);

            $target = null;
            if (null !== $placement['target']) {
                $target = $teams[$placement['target']];
            }

            $usage = (new TeamJokerUsage())
                ->setTeam($team)
                ->setJoker($joker)
                ->setMatch($match)
                ->setTargetTeam($target);
            $this->entityManager->persist($usage);
        }

        $this->entityManager->flush();

        $buteurIds = [];
        foreach (JokerTestScenarioDefinition::GOAL_SCORER_COUNTRY as $goalKey => $countryName) {
            $buteur = $this->requireButeurForCountry($countryName);
            $id = $buteur->getId();
            if (null !== $id) {
                $buteurIds[$goalKey] = (int) $id;
            }
        }

        $teamIds = [];
        foreach ($teams as $key => $team) {
            $id = $team->getId();
            if (null !== $id) {
                $teamIds[$key] = (int) $id;
            }
        }

        $this->stateStore->write([
            'step_index' => 0,
            'match_ids' => $matchIds,
            'team_ids' => $teamIds,
            'buteur_ids' => $buteurIds,
            'seeded_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);

        return [
            'teams' => $teamIds,
            'match_ids' => $matchIds,
            'buteur_ids' => $buteurIds,
        ];
    }

    private function requireCountry(string $nom): Country
    {
        $country = $this->countryRepository->findOneBy(['nom' => $nom]);
        if (!$country instanceof Country) {
            throw new \RuntimeException(sprintf(
                'Pays « %s » introuvable. Importez les sélections (app:import:fifa-qualified-teams).',
                $nom,
            ));
        }

        return $country;
    }

    private function requireButeurForCountry(string $countryName): Buteur
    {
        $buteur = $this->buteurRepository->createQueryBuilder('b')
            ->innerJoin('b.pays', 'p')
            ->andWhere('p.nom = :nom')
            ->setParameter('nom', $countryName)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$buteur instanceof Buteur) {
            throw new \RuntimeException(sprintf(
                'Aucun buteur pour le pays « %s ». Vérifiez l’import des buteurs.',
                $countryName,
            ));
        }

        return $buteur;
    }

    /**
     * @param array<string, Joker> $jokersByCode
     */
    private function requireJoker(array $jokersByCode, string $code): Joker
    {
        if (!isset($jokersByCode[$code])) {
            throw new \RuntimeException(sprintf('Joker « %s » introuvable ou inactif en base.', $code));
        }

        return $jokersByCode[$code];
    }
}
