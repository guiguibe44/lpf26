<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ForumPostMention;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ForumPostMention>
 */
class ForumPostMentionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumPostMention::class);
    }
}
