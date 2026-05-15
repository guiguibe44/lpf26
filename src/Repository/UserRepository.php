<?php

namespace App\Repository;

use App\Entity\GameMatch;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Joueurs à jour de cotisation, sans pronostic sur ce match.
     *
     * @return list<User>
     */
    public function findPlayersWithoutPronosticForMatch(GameMatch $match): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.cotisationPayee = :paid')
            ->andWhere('NOT EXISTS (
                SELECT 1 FROM App\Entity\Pronostic p
                WHERE p.joueur = u AND p.match = :match
            )')
            ->setParameter('paid', true)
            ->setParameter('match', $match)
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Joueurs avec cotisation payée (sélection admin).
     *
     * @return list<User>
     */
    public function findActivePlayersOrderedByEmail(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.cotisationPayee = :paid')
            ->setParameter('paid', true)
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return User[] Returns an array of User objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?User
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
