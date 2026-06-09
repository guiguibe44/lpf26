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
use App\Service\BadgeFeature;
use App\Service\UploadPathHelper;

final class BadgeDisplayBuilder
{
    public function __construct(
        private readonly BadgeFeature $badgeFeature,
        private readonly BadgeAwardRepository $badgeAwardRepository,
        private readonly BadgeDefinitionRepository $badgeDefinitionRepository,
    ) {
    }

    public function isAccountDesignPreview(User $user): bool
    {
        return $this->badgeFeature->isAccountDesignPreview($user);
    }

    /**
     * @return list<array{
     *     name: string,
     *     image: ?string,
     *     icon: ?string,
     *     flavorText: ?string,
     *     category: string,
     *     awardedAt: \DateTimeImmutable,
     *     isPreview: bool,
     *     ironic: bool,
     *     outcome: ?string
     * }>
     */
    public function buildForUser(User $user): array
    {
        if ($this->badgeFeature->isAccountDesignPreview($user)) {
            return $this->buildCataloguePreview(BadgeScope::Player);
        }

        if (!$this->badgeFeature->isActiveForUser($user)) {
            return [];
        }

        return array_map(
            fn (BadgeAward $award): array => $this->rowFromAward($award),
            $this->badgeAwardRepository->findForUserOrdered($user),
        );
    }

    /**
     * @return list<array{
     *     name: string,
     *     image: ?string,
     *     icon: ?string,
     *     flavorText: ?string,
     *     category: string,
     *     awardedAt: \DateTimeImmutable,
     *     isPreview: bool,
     *     ironic: bool,
     *     outcome: ?string
     * }>
     */
    public function buildForTeam(Team $team, ?User $viewer = null): array
    {
        if ($viewer instanceof User && $this->badgeFeature->isAccountDesignPreview($viewer)) {
            return $this->buildCataloguePreview(BadgeScope::Team);
        }

        if (!$viewer instanceof User || !$this->badgeFeature->isActiveForUser($viewer)) {
            return [];
        }

        return array_map(
            fn (BadgeAward $award): array => $this->rowFromAward($award),
            $this->badgeAwardRepository->findForTeamOrdered($team),
        );
    }

    /**
     * @return list<array{
     *     name: string,
     *     image: ?string,
     *     icon: ?string,
     *     flavorText: ?string,
     *     category: string,
     *     awardedAt: \DateTimeImmutable,
     *     isPreview: bool,
     *     ironic: bool,
     *     outcome: ?string
     * }>
     */
    private function buildCataloguePreview(BadgeScope $scope): array
    {
        $rows = [];
        foreach ($this->badgeDefinitionRepository->findActiveOrdered() as $definition) {
            if ($definition->getScope() !== $scope) {
                continue;
            }

            $rows[] = $this->rowFromDefinition($definition, true);
        }

        return $rows;
    }

    /**
     * @return array{
     *     name: string,
     *     image: ?string,
     *     icon: ?string,
     *     flavorText: ?string,
     *     category: string,
     *     awardedAt: \DateTimeImmutable,
     *     isPreview: bool,
     *     ironic: bool,
     *     outcome: ?string
     * }
     */
    private function rowFromDefinition(BadgeDefinition $badge, bool $isPreview): array
    {
        return [
            'name' => (string) $badge->getName(),
            'image' => UploadPathHelper::publicPath($badge->getImage(), BadgeDefinition::UPLOAD_SUBDIR),
            'icon' => $badge->getIcon(),
            'flavorText' => $badge->getFlavorText(),
            'category' => $badge->getCategory()->label(),
            'awardedAt' => new \DateTimeImmutable(),
            'isPreview' => $isPreview,
            'ironic' => $badge->isIronic(),
            'outcome' => $badge->getOutcome()?->value,
        ];
    }

    /**
     * @return array{
     *     name: string,
     *     image: ?string,
     *     icon: ?string,
     *     flavorText: ?string,
     *     category: string,
     *     awardedAt: \DateTimeImmutable,
     *     isPreview: bool,
     *     ironic: bool,
     *     outcome: ?string
     * }
     */
    private function rowFromAward(BadgeAward $award): array
    {
        $badge = $award->getBadgeDefinition();
        if (!$badge instanceof BadgeDefinition) {
            return [
                'name' => 'Badge',
                'image' => null,
                'icon' => null,
                'flavorText' => null,
                'category' => '',
                'awardedAt' => $award->getAwardedAt(),
                'isPreview' => false,
                'ironic' => false,
                'outcome' => null,
            ];
        }

        if ($badge->isIronic() && !$this->badgeFeature->isIronicEnabled()) {
            return [
                'name' => $badge->getName() ?? '',
                'image' => null,
                'icon' => null,
                'flavorText' => null,
                'category' => $badge->getCategory()->label(),
                'awardedAt' => $award->getAwardedAt(),
                'isPreview' => false,
                'ironic' => true,
                'outcome' => $badge->getOutcome()?->value,
            ];
        }

        return [
            'name' => (string) $badge->getName(),
            'image' => UploadPathHelper::publicPath($badge->getImage(), BadgeDefinition::UPLOAD_SUBDIR),
            'icon' => $badge->getIcon(),
            'flavorText' => $badge->getFlavorText(),
            'category' => $badge->getCategory()->label(),
            'awardedAt' => $award->getAwardedAt(),
            'isPreview' => false,
            'ironic' => $badge->isIronic(),
            'outcome' => $badge->getOutcome()?->value,
        ];
    }
}
