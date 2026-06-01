<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TeamRecapGif;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class TeamRecapGifUrlBuilder
{
    public function __construct(
        #[Autowire('%env(DEFAULT_URI)%')]
        private readonly string $defaultUri,
    ) {
    }

    public function toAbsoluteUrl(string $storedPath): string
    {
        if (str_starts_with($storedPath, 'http://') || str_starts_with($storedPath, 'https://')) {
            return $storedPath;
        }

        $public = UploadPathHelper::publicPath($storedPath, TeamRecapGif::UPLOAD_SUBDIR);
        if (null === $public) {
            $public = str_starts_with($storedPath, '/')
                ? $storedPath
                : '/uploads/'.TeamRecapGif::UPLOAD_SUBDIR.'/'.$storedPath;
        }

        if (str_starts_with($public, 'http://') || str_starts_with($public, 'https://')) {
            return $public;
        }

        return rtrim($this->defaultUri, '/').$public;
    }
}
