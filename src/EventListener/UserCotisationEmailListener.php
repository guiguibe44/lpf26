<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Service\CotisationValidatedNotifier;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * Envoie un e-mail au joueur quand cotisationPayee passe à true (souvent via admin EasyAdmin).
 */
final class UserCotisationEmailListener
{
    /** @var array<int, int> */
    private array $userIdsToNotify = [];

    public function __construct(
        private readonly CotisationValidatedNotifier $cotisationValidatedNotifier,
    ) {
    }

    #[AsEntityListener(event: Events::preUpdate, entity: User::class)]
    public function preUpdate(User $user, PreUpdateEventArgs $args): void
    {
        if (!$args->hasChangedField('cotisationPayee')) {
            return;
        }

        if (true === $args->getOldValue('cotisationPayee')) {
            return;
        }

        if (true !== $args->getNewValue('cotisationPayee')) {
            return;
        }

        $this->queueUser($user);
    }

    #[AsEntityListener(event: Events::postPersist, entity: User::class)]
    public function postPersist(User $user, PostPersistEventArgs $args): void
    {
        if (!$user->isCotisationPayee()) {
            return;
        }

        $this->queueUser($user);
    }

    #[AsDoctrineListener(event: Events::postFlush)]
    public function postFlush(PostFlushEventArgs $args): void
    {
        if ([] === $this->userIdsToNotify) {
            return;
        }

        $ids = array_values($this->userIdsToNotify);
        $this->userIdsToNotify = [];

        $em = $args->getObjectManager();
        foreach ($ids as $id) {
            $user = $em->find(User::class, $id);
            if ($user instanceof User && $user->isCotisationPayee()) {
                $this->cotisationValidatedNotifier->notify($user);
            }
        }
    }

    private function queueUser(User $user): void
    {
        $id = $user->getId();
        if (null !== $id) {
            $this->userIdsToNotify[$id] = $id;
        }
    }
}
