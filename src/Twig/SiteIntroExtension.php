<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\SiteIntroSlide;
use App\Repository\SiteIntroSlideRepository;
use Twig\Attribute\AsTwigFunction;

final class SiteIntroExtension
{
    public function __construct(
        private readonly SiteIntroSlideRepository $siteIntroSlideRepository,
    ) {
    }

    /**
     * @return list<SiteIntroSlide>
     */
    #[AsTwigFunction('site_intro_slides')]
    public function slides(): array
    {
        return $this->siteIntroSlideRepository->findActiveOrdered();
    }
}
