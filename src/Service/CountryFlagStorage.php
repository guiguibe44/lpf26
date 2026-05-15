<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Country;

/**
 * Télécharge les drapeaux distants (API-Sports) vers public/uploads/drapeaux/.
 */
final class CountryFlagStorage
{
    private const UPLOAD_SUBDIR = 'drapeaux';

    public function __construct(
        private readonly RemoteImageStorage $remoteImageStorage,
    ) {
    }

    /**
     * @return bool true si un fichier local a été enregistré sur {@see Country::setDrapeau()}
     */
    public function storeFlagForCountry(Country $country, ?string $sourceUrl = null): bool
    {
        $url = $sourceUrl ?? $country->getDrapeau();
        if (!$this->remoteImageStorage->isRemoteUrl($url)) {
            return false;
        }

        $localPath = $this->remoteImageStorage->download(
            self::UPLOAD_SUBDIR,
            (string) $country->getNom(),
            $url,
        );
        if (null === $localPath) {
            return false;
        }

        $this->remoteImageStorage->deleteLocalFile($country->getDrapeau(), self::UPLOAD_SUBDIR);
        $country->setDrapeau($localPath);

        return true;
    }

    public function isRemoteUrl(?string $path): bool
    {
        return $this->remoteImageStorage->isRemoteUrl($path);
    }

    public function isLocalUpload(?string $path): bool
    {
        return $this->remoteImageStorage->isLocalUpload($path, self::UPLOAD_SUBDIR);
    }
}
