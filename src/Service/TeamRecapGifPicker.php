<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\TeamRecapGifRepository;

final class TeamRecapGifPicker
{
    public function __construct(
        private readonly TeamRecapGifRepository $teamRecapGifRepository,
        private readonly TeamRecapGifUrlBuilder $teamRecapGifUrlBuilder,
    ) {
    }

    public function pickRandomAbsoluteUrl(string $slot): ?string
    {
        $paths = $this->teamRecapGifRepository->findActivePathsBySlot($slot);
        if ([] === $paths) {
            return null;
        }

        $path = $paths[random_int(0, \count($paths) - 1)];

        return $this->teamRecapGifUrlBuilder->toAbsoluteUrl($path);
    }

    public function pickRandomAbsoluteUrlAny(): ?string
    {
        $paths = $this->teamRecapGifRepository->findAllActivePaths();
        if ([] === $paths) {
            return null;
        }

        $path = $paths[random_int(0, \count($paths) - 1)];

        return $this->teamRecapGifUrlBuilder->toAbsoluteUrl($path);
    }
}
