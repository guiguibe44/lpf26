<?php

declare(strict_types=1);

namespace App\Service\Badge;

use App\Entity\BadgeAward;
use App\Entity\BadgeDefinition;
use App\Entity\Team;
use App\Entity\User;
use App\Enum\BadgeCategory;
use App\Enum\BadgeScope;
use App\Repository\BadgeAwardRepository;
use App\Repository\BadgeDefinitionRepository;
use App\Service\BadgeFeature;
use App\Service\UploadPathHelper;

/**
 * Grille de collection badges (acquis + emplacements verrouillés).
 */
final class BadgeCollectionBuilder
{
    public function __construct(
        private readonly BadgeFeature $badgeFeature,
        private readonly BadgeDefinitionRepository $badgeDefinitionRepository,
        private readonly BadgeAwardRepository $badgeAwardRepository,
    ) {
    }

    public function shouldShowAccountTab(User $user): bool
    {
        return $this->badgeFeature->isActiveForUser($user)
            || $this->badgeFeature->isAccountDesignPreview($user);
    }

    /**
     * @return array{
     *     designPreview: bool,
     *     player: array{sections: list<array{category: string, slots: list<array<string, mixed>>>, earned: int, total: int},
     *     team: array{sections: list<array{category: string, slots: list<array<string, mixed>>>, earned: int, total: int}
     * }
     */
    public function buildAccountView(User $user, ?Team $team): array
    {
        $designPreview = $this->badgeFeature->isAccountDesignPreview($user);

        if (!$this->badgeFeature->isEnabled() && !$designPreview) {
            return $this->emptyView(false);
        }

        if ($designPreview && !$this->badgeFeature->isActiveForUser($user)) {
            return [
                'designPreview' => true,
                'player' => $this->buildPreviewScope(BadgeScope::Player),
                'team' => $this->buildPreviewScope(BadgeScope::Team),
            ];
        }

        return [
            'designPreview' => false,
            'player' => $this->buildScopeCollection(
                BadgeScope::Player,
                $this->badgeAwardRepository->findEarnedIndexedByCodeForUser($user),
            ),
            'team' => $team instanceof Team
                ? $this->buildScopeCollection(
                    BadgeScope::Team,
                    $this->badgeAwardRepository->findEarnedIndexedByCodeForTeam($team),
                )
                : $this->emptyScope(),
        ];
    }

    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     image: ?string,
     *     icon: ?string,
     *     scope: string,
     *     category: string
     * }>
     */
    public function buildUnseenNotifications(User $user, ?Team $team): array
    {
        if (!$this->badgeFeature->isActiveForUser($user)) {
            return [];
        }

        $rows = [];
        foreach ($this->badgeAwardRepository->findUnseenForUserAndTeam($user, $team) as $award) {
            $row = $this->notificationFromAward($award);
            if (null !== $row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return array{id: int, name: string, image: ?string, icon: ?string, scope: string, category: string}|null
     */
    public function notificationFromAward(BadgeAward $award): ?array
    {
        return $this->notificationRowFromAward($award);
    }

    /**
     * @param array<string, BadgeAward> $earnedByCode
     *
     * @return array{sections: list<array{category: string, slots: list<array<string, mixed>>>, earned: int, total: int}
     */
    private function buildScopeCollection(BadgeScope $scope, array $earnedByCode): array
    {
        /** @var array<string, list<BadgeDefinition>> $byCategory */
        $byCategory = [];
        foreach ($this->badgeDefinitionRepository->findActiveOrdered() as $definition) {
            if ($definition->getScope() !== $scope) {
                continue;
            }
            $byCategory[$definition->getCategory()->value][] = $definition;
        }

        $sections = [];
        $earned = 0;
        $total = 0;

        foreach (BadgeCategory::cases() as $category) {
            $definitions = $byCategory[$category->value] ?? [];
            if ([] === $definitions) {
                continue;
            }

            $slots = [];
            foreach ($definitions as $definition) {
                ++$total;
                $code = (string) $definition->getCode();
                $award = $earnedByCode[$code] ?? null;
                if ($award instanceof BadgeAward) {
                    ++$earned;
                    $slots[] = $this->slotFromAward($definition, $award);
                } else {
                    $slots[] = $this->lockedSlot($definition);
                }
            }

            $sections[] = [
                'category' => $category->label(),
                'slots' => $slots,
            ];
        }

        return [
            'sections' => $sections,
            'earned' => $earned,
            'total' => $total,
        ];
    }

    /**
     * @return array{sections: list<array{category: string, slots: list<array<string, mixed>>>, earned: int, total: int}
     */
    private function buildPreviewScope(BadgeScope $scope): array
    {
        /** @var array<string, list<BadgeDefinition>> $byCategory */
        $byCategory = [];
        foreach ($this->badgeDefinitionRepository->findActiveOrdered() as $definition) {
            if ($definition->getScope() !== $scope) {
                continue;
            }
            $byCategory[$definition->getCategory()->value][] = $definition;
        }

        $sections = [];
        $total = 0;

        foreach (BadgeCategory::cases() as $category) {
            $definitions = $byCategory[$category->value] ?? [];
            if ([] === $definitions) {
                continue;
            }

            $slots = [];
            foreach ($definitions as $definition) {
                ++$total;
                $slots[] = $this->slotFromDefinition($definition);
            }

            $sections[] = [
                'category' => $category->label(),
                'slots' => $slots,
            ];
        }

        return [
            'sections' => $sections,
            'earned' => $total,
            'total' => $total,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function slotFromDefinition(BadgeDefinition $definition): array
    {
        return [
            'code' => (string) $definition->getCode(),
            'earned' => true,
            'awardId' => null,
            'name' => (string) $definition->getName(),
            'image' => UploadPathHelper::publicPath($definition->getImage(), BadgeDefinition::UPLOAD_SUBDIR),
            'icon' => $definition->getIcon(),
            'flavorText' => $definition->getFlavorText(),
            'category' => $definition->getCategory()->label(),
            'awardedAt' => new \DateTimeImmutable(),
            'ironic' => $definition->isIronic(),
            'outcome' => $definition->getOutcome()?->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function slotFromAward(BadgeDefinition $definition, BadgeAward $award): array
    {
        $badge = $award->getBadgeDefinition() ?? $definition;
        $hideIronicDetails = $badge->isIronic() && !$this->badgeFeature->isIronicEnabled();

        return [
            'code' => (string) $definition->getCode(),
            'earned' => true,
            'awardId' => (int) $award->getId(),
            'name' => (string) $badge->getName(),
            'image' => $hideIronicDetails ? null : UploadPathHelper::publicPath($badge->getImage(), BadgeDefinition::UPLOAD_SUBDIR),
            'icon' => $hideIronicDetails ? null : $badge->getIcon(),
            'flavorText' => $hideIronicDetails ? null : $badge->getFlavorText(),
            'category' => $definition->getCategory()->label(),
            'awardedAt' => $award->getAwardedAt(),
            'ironic' => $badge->isIronic(),
            'outcome' => $badge->getOutcome()?->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lockedSlot(BadgeDefinition $definition): array
    {
        return [
            'code' => (string) $definition->getCode(),
            'earned' => false,
            'awardId' => null,
            'name' => null,
            'image' => null,
            'icon' => null,
            'flavorText' => null,
            'category' => $definition->getCategory()->label(),
            'awardedAt' => null,
            'ironic' => false,
            'outcome' => $definition->getOutcome()?->value,
        ];
    }

    /**
     * @return array{id: int, name: string, image: ?string, icon: ?string, scope: string, category: string}|null
     */
    private function notificationRowFromAward(BadgeAward $award): ?array
    {
        $badge = $award->getBadgeDefinition();
        if (!$badge instanceof BadgeDefinition) {
            return null;
        }

        if ($badge->isIronic() && !$this->badgeFeature->isIronicEnabled()) {
            return null;
        }

        return [
            'id' => (int) $award->getId(),
            'name' => (string) $badge->getName(),
            'image' => UploadPathHelper::publicPath($badge->getImage(), BadgeDefinition::UPLOAD_SUBDIR),
            'icon' => $badge->getIcon(),
            'scope' => $badge->getScope()->value,
            'category' => $badge->getCategory()->label(),
        ];
    }

    /**
     * @return array{
     *     designPreview: bool,
     *     player: array{sections: list<array{category: string, slots: list<array<string, mixed>>>, earned: int, total: int},
     *     team: array{sections: list<array{category: string, slots: list<array<string, mixed>>>, earned: int, total: int}
     * }
     */
    private function emptyView(bool $designPreview): array
    {
        return [
            'designPreview' => $designPreview,
            'player' => $this->emptyScope(),
            'team' => $this->emptyScope(),
        ];
    }

    /**
     * @return array{sections: list<array{category: string, slots: list<array<string, mixed>>>, earned: int, total: int}
     */
    private function emptyScope(): array
    {
        return [
            'sections' => [],
            'earned' => 0,
            'total' => 0,
        ];
    }
}
