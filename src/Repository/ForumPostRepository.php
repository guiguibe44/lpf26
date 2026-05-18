<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ForumPost;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ForumPost>
 */
class ForumPostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumPost::class);
    }

    /**
     * Messages racine récents, avec réponses et auteurs.
     *
     * @return list<ForumPost>
     */
    public function findRecentRootPosts(int $limit = 80): array
    {
        /** @var list<ForumPost> $posts */
        $posts = $this->createQueryBuilder('p')
            ->andWhere('p.parent IS NULL')
            ->leftJoin('p.replies', 'r')->addSelect('r')
            ->leftJoin('p.author', 'a')->addSelect('a')
            ->leftJoin('r.author', 'ra')->addSelect('ra')
            ->orderBy('p.createdAt', 'DESC')
            ->addOrderBy('r.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $posts;
    }
}
