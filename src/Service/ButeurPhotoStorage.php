<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Buteur;

final class ButeurPhotoStorage
{
    private const UPLOAD_SUBDIR = 'buteurs';

    public function __construct(
        private readonly RemoteImageStorage $remoteImageStorage,
    ) {
    }

    public function storePhotoForButeur(Buteur $buteur, ?string $sourceUrl = null): bool
    {
        $url = $sourceUrl ?? $buteur->getPhoto();
        if (!$this->remoteImageStorage->isRemoteUrl($url)) {
            return false;
        }

        $slug = trim(sprintf('%s-%s', (string) $buteur->getPrenom(), (string) $buteur->getNom()));
        $localPath = $this->remoteImageStorage->download(self::UPLOAD_SUBDIR, $slug, $url);
        if (null === $localPath) {
            return false;
        }

        $this->remoteImageStorage->deleteLocalFile($buteur->getPhoto(), self::UPLOAD_SUBDIR);
        $buteur->setPhoto($localPath);

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
