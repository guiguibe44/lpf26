<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\But;
use App\Entity\GameMatch;
use App\Entity\Pronostic;
use App\Entity\Team;
use App\Entity\TeamInvitation;
use App\Entity\TeamJokerUsage;
use App\Entity\TeamMember;
use App\Entity\ResetPasswordRequest;
use App\Entity\TeamRankingSnapshot;
use App\Entity\User;
use App\Data\JokerTestScenarioDefinition;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Supprime équipes, joueurs non-admin et données liées (local / recette jokers).
 */
final class JokerTestScenarioResetService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly JokerTestScenarioStateStore $stateStore,
    ) {
    }

    /**
     * @return array{teams: int, users: int, matches: int, pronostics: int}
     */
    public function reset(): array
    {
        $this->stateStore->clear();

        $counts = [
            'teams' => 0,
            'users' => 0,
            'matches' => 0,
            'pronostics' => 0,
        ];

        $this->deleteTestMatches($counts);
        $this->deleteAllTeamJokerUsages();
        $this->deleteAllRankingSnapshots();
        $this->deleteAllTeamInvitations();
        $this->deleteAllTeamMembers();
        $counts['pronostics'] = $this->deletePronosticsForNonAdmins();
        $counts['teams'] = $this->deleteAllTeams();
        $counts['users'] = $this->deleteNonAdminUsers();

        $this->entityManager->flush();

        return $counts;
    }

    /**
     * @param array{teams: int, users: int, matches: int, pronostics: int} $counts
     */
    private function deleteTestMatches(array &$counts): void
    {
        $matches = $this->entityManager->getRepository(GameMatch::class)->findBy([
            'venueName' => JokerTestScenarioDefinition::MATCH_MARKER,
        ]);

        foreach ($matches as $match) {
            $this->deleteMatchDependencies($match);
            $this->entityManager->remove($match);
            ++$counts['matches'];
        }
    }

    private function deleteMatchDependencies(GameMatch $match): void
    {
        $buts = $this->entityManager->getRepository(But::class)->findBy(['matchRef' => $match]);
        foreach ($buts as $but) {
            $this->entityManager->remove($but);
        }

        $pronostics = $this->entityManager->getRepository(Pronostic::class)->findBy(['match' => $match]);
        foreach ($pronostics as $pronostic) {
            $this->entityManager->remove($pronostic);
        }

        $usages = $this->entityManager->getRepository(TeamJokerUsage::class)->findBy(['match' => $match]);
        foreach ($usages as $usage) {
            $this->entityManager->remove($usage);
        }

        $snapshots = $this->entityManager->getRepository(TeamRankingSnapshot::class)->findBy(['matchRef' => $match]);
        foreach ($snapshots as $snapshot) {
            $this->entityManager->remove($snapshot);
        }
    }

    private function deleteAllTeamJokerUsages(): void
    {
        $this->entityManager->createQuery('DELETE FROM App\Entity\TeamJokerUsage u')->execute();
    }

    private function deleteAllRankingSnapshots(): void
    {
        $this->entityManager->createQuery('DELETE FROM App\Entity\TeamRankingSnapshot s')->execute();
    }

    private function deleteAllTeamInvitations(): void
    {
        $this->entityManager->createQuery('DELETE FROM App\Entity\TeamInvitation i')->execute();
    }

    private function deleteAllTeamMembers(): void
    {
        $this->entityManager->createQuery('DELETE FROM App\Entity\TeamMember m')->execute();
    }

    private function deletePronosticsForNonAdmins(): int
    {
        $adminIds = $this->findAdminUserIds();
        $qb = $this->entityManager->createQueryBuilder()
            ->delete(Pronostic::class, 'p');

        if ([] !== $adminIds) {
            $qb->andWhere('IDENTITY(p.joueur) NOT IN (:adminIds)')
                ->setParameter('adminIds', $adminIds);
        }

        return (int) $qb->getQuery()->execute();
    }

    private function deleteAllTeams(): int
    {
        return (int) $this->entityManager->createQuery('DELETE FROM App\Entity\Team t')->execute();
    }

    private function deleteNonAdminUsers(): int
    {
        $adminIds = $this->findAdminUserIds();
        $qb = $this->entityManager->createQueryBuilder()
            ->delete(ResetPasswordRequest::class, 'r');

        if ([] !== $adminIds) {
            $qb->andWhere('IDENTITY(r.user) NOT IN (:adminIds)')
                ->setParameter('adminIds', $adminIds);
        }

        $qb->getQuery()->execute();

        $users = $this->entityManager->getRepository(User::class)->findAll();
        $removed = 0;

        foreach ($users as $user) {
            $userId = $user->getId();
            if (null === $userId || \in_array((int) $userId, $adminIds, true)) {
                continue;
            }

            $this->entityManager->remove($user);
            ++$removed;
        }

        return $removed;
    }

    /**
     * @return list<int>
     */
    private function findAdminUserIds(): array
    {
        $ids = [];
        foreach ($this->entityManager->getRepository(User::class)->findAll() as $user) {
            if (\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                $id = $user->getId();
                if (null !== $id) {
                    $ids[] = (int) $id;
                }
            }
        }

        return $ids;
    }
}
