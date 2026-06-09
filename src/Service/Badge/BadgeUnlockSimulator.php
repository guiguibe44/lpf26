<?php

declare(strict_types=1);

namespace App\Service\Badge;

use App\Entity\BadgeAward;
use App\Entity\BadgeDefinition;
use App\Entity\Team;
use App\Entity\User;
use App\Enum\BadgeScope;
use App\Repository\BadgeAwardRepository;
use App\Repository\BadgeDefinitionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Dev/local : prépare un badge « non vu » pour tester l’animation de déblocage.
 */
final class BadgeUnlockSimulator
{
    public function __construct(
        private readonly BadgeAwardRepository $badgeAwardRepository,
        private readonly BadgeDefinitionRepository $badgeDefinitionRepository,
        private readonly BadgeCollectionBuilder $badgeCollectionBuilder,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{id: int, name: string, image: ?string, icon: ?string, scope: string, category: string}
     */
    public function prepareUnseenNotification(User $user, ?Team $team): array
    {
        $award = $this->resolveAwardForSimulation($user, $team);
        $award->setSeenAt(null);
        $this->entityManager->flush();

        $row = $this->badgeCollectionBuilder->notificationFromAward($award);
        if (null === $row) {
            throw new \RuntimeException('Impossible de construire la notification badge.');
        }

        return $row;
    }

    private function resolveAwardForSimulation(User $user, ?Team $team): BadgeAward
    {
        $existing = $this->badgeAwardRepository->findAllForUserAndTeam($user, $team);
        if ([] !== $existing) {
            return $existing[random_int(0, \count($existing) - 1)];
        }

        foreach ($this->badgeDefinitionRepository->findActiveOrdered() as $definition) {
            if (BadgeScope::Player !== $definition->getScope()) {
                continue;
            }
            if ($this->badgeAwardRepository->hasAwardForUser($definition, $user)) {
                continue;
            }

            $award = (new BadgeAward())
                ->setBadgeDefinition($definition)
                ->setUser($user)
                ->setMetadata(['simulated' => true]);

            $this->entityManager->persist($award);
            $this->entityManager->flush();

            return $award;
        }

        throw new \RuntimeException('Aucun badge disponible pour la simulation.');
    }
}
