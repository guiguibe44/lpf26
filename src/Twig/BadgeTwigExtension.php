<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Service\BadgeFeature;
use Twig\Attribute\AsTwigFunction;

final class BadgeTwigExtension
{
    public function __construct(
        private readonly BadgeFeature $badgeFeature,
    ) {
    }

    #[AsTwigFunction('badges_active_for')]
    public function badgesActiveFor(?User $user): bool
    {
        return $user instanceof User && $this->badgeFeature->isActiveForUser($user);
    }
}
