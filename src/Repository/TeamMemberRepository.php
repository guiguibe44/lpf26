<?php

namespace App\Repository;

use App\Entity\Buteur;
use App\Entity\TeamMember;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TeamMember>
 */
class TeamMemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeamMember::class);
    }

    /**
     * @return array<int,int> Map playerId => teamId
     */
    public function findPlayerTeamMap(): array
    {
        $rows = $this->createQueryBuilder('tm')
            ->select('IDENTITY(tm.player) AS playerId')
            ->addSelect('IDENTITY(tm.team) AS teamId')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $playerId = (int) ($row['playerId'] ?? 0);
            $teamId = (int) ($row['teamId'] ?? 0);
            if ($playerId > 0 && $teamId > 0) {
                $map[$playerId] = $teamId;
            }
        }

        return $map;
    }

    /**
     * @return list<int>
     */
    /**
     * Joueurs mentionnables (@surnom) pour le forum.
     *
     * @return list<array{id: int, nickname: string, team_name: string, avatar: string|null}>
     */
    public function searchPlayersForForumMention(string $query, ?int $excludeUserId = null, int $limit = 10): array
    {
        $query = trim($query);

        $qb = $this->createQueryBuilder('tm')
            ->select('u.id AS id', 'tm.nickname AS nickname', 't.name AS team_name', 'u.avatar AS avatar')
            ->innerJoin('tm.player', 'u')
            ->innerJoin('tm.team', 't')
            ->orderBy('tm.nickname', 'ASC')
            ->setMaxResults($limit);

        if ('' !== $query) {
            $qb->andWhere('LOWER(tm.nickname) LIKE :q')
                ->setParameter('q', mb_strtolower($query).'%');
        }

        if (null !== $excludeUserId && $excludeUserId > 0) {
            $qb->andWhere('u.id != :exclude')->setParameter('exclude', $excludeUserId);
        }

        $rows = $qb->getQuery()->getArrayResult();
        $results = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $results[] = [
                'id' => $id,
                'nickname' => (string) ($row['nickname'] ?? ''),
                'team_name' => (string) ($row['team_name'] ?? ''),
                'avatar' => isset($row['avatar']) ? (string) $row['avatar'] : null,
            ];
        }

        return $results;
    }

    public function findPartnerPlayerIds(User $player): array
    {
        $member = $this->findOneBy(['player' => $player]);
        $team = $member?->getTeam();
        $playerId = $player->getId();
        if (null === $team || null === $playerId) {
            return [];
        }

        $rows = $this->createQueryBuilder('tm')
            ->select('IDENTITY(tm.player) AS playerId')
            ->andWhere('tm.team = :team')
            ->andWhere('IDENTITY(tm.player) != :playerId')
            ->setParameter('team', $team)
            ->setParameter('playerId', $playerId)
            ->getQuery()
            ->getArrayResult();

        $partnerIds = [];
        foreach ($rows as $row) {
            $partnerId = (int) ($row['playerId'] ?? 0);
            if ($partnerId > 0) {
                $partnerIds[] = $partnerId;
            }
        }

        return $partnerIds;
    }

    /**
     * Membres de la même équipe que le joueur (hors lui), avec buteur choisi et pays du buteur.
     *
     * @return list<User>
     */
    public function findPartnerUsers(User $player): array
    {
        $ids = $this->findPartnerPlayerIds($player);
        if ([] === $ids) {
            return [];
        }

        return $this->getEntityManager()->createQueryBuilder()
            ->select('u', 'b', 'bp')
            ->from(User::class, 'u')
            ->leftJoin('u.buteurChoisi', 'b')
            ->leftJoin('b.pays', 'bp')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Buteurs déjà choisis par au moins un joueur d'une autre équipe (distinct).
     *
     * @return list<Buteur>
     */
    public function findButeursChoisisParAutresEquipes(User $player): array
    {
        $member = $this->findOneBy(['player' => $player]);
        $team = $member?->getTeam();
        if (null === $team) {
            return [];
        }

        $rows = $this->createQueryBuilder('tm')
            ->select('DISTINCT IDENTITY(u.buteurChoisi) AS bid')
            ->innerJoin('tm.player', 'u')
            ->andWhere('tm.team != :myTeam')
            ->andWhere('u.buteurChoisi IS NOT NULL')
            ->setParameter('myTeam', $team)
            ->getQuery()
            ->getScalarResult();

        $buteurIds = [];
        foreach ($rows as $row) {
            $id = isset($row['bid']) ? (int) $row['bid'] : 0;
            if ($id > 0) {
                $buteurIds[] = $id;
            }
        }

        if ([] === $buteurIds) {
            return [];
        }

        return $this->getEntityManager()->createQueryBuilder()
            ->select('b', 'p')
            ->from(Buteur::class, 'b')
            ->leftJoin('b.pays', 'p')
            ->where('b.id IN (:ids)')
            ->setParameter('ids', $buteurIds)
            ->orderBy('b.nom', 'ASC')
            ->addOrderBy('b.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
