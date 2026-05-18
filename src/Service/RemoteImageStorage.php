<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Télécharge une image distante vers public/uploads/{subdir}/.
 */
final class RemoteImageStorage
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly SluggerInterface $slugger,
        private readonly string $projectDir,
        private readonly ImageUploadOptimizer $imageUploadOptimizer,
    ) {
    }

    public function isRemoteUrl(?string $path): bool
    {
        return null !== $path
            && ('' !== $path)
            && (str_starts_with($path, 'http://') || str_starts_with($path, 'https://'));
    }

    public function isLocalUpload(?string $path, string $subdir): bool
    {
        return UploadPathHelper::isLocalUpload($path, $subdir);
    }

    /**
     * @return string|null Chemin public /uploads/{subdir}/…
     */
    public function download(string $subdir, string $basenameSlug, string $url): ?string
    {
        if (!$this->isRemoteUrl($url)) {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 20,
                'max_redirects' => 3,
                'headers' => [
                    'User-Agent' => 'LPF26/1.0',
                    'Accept' => 'image/*',
                ],
            ]);

            if (200 !== $response->getStatusCode()) {
                return null;
            }

            $content = $response->getContent(false);
            if ('' === $content) {
                return null;
            }

            $contentType = $response->getHeaders()['content-type'][0] ?? null;
            $extension = $this->resolveExtension($url, $contentType, $content);
            if (null === $extension) {
                return null;
            }

            $uploadRoot = $this->projectDir.'/public/uploads/'.$subdir;
            if (!is_dir($uploadRoot) && !mkdir($uploadRoot, 0775, true) && !is_dir($uploadRoot)) {
                return null;
            }

            $slug = (string) $this->slugger->slug($basenameSlug);
            if ('' === $slug) {
                $slug = 'image';
            }

            $filename = sprintf('%s-%s.%s', $slug, bin2hex(random_bytes(6)), $extension);
            $absolutePath = $uploadRoot.'/'.$filename;

            if (false === file_put_contents($absolutePath, $content)) {
                return null;
            }

            $optimized = $this->imageUploadOptimizer->optimizeStoredFilename($filename, $subdir);

            return $optimized ?? $filename;
        } catch (\Throwable) {
            return null;
        }
    }

    public function deleteLocalFile(?string $stored, string $subdir): void
    {
        $publicPath = UploadPathHelper::publicPath($stored, $subdir);
        if (null === $publicPath || !UploadPathHelper::isLocalUpload($stored, $subdir)) {
            return;
        }

        $absolutePath = $this->projectDir.'/public'.$publicPath;
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    private function resolveExtension(string $url, ?string $contentType, string $content): ?string
    {
        $mime = $this->detectMimeType($contentType, $content);
        $fromMime = match ($mime) {
            'image/png' => 'png',
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            default => null,
        };

        if (null !== $fromMime) {
            return $fromMime;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (\is_string($path)) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (\in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'], true)) {
                return 'jpeg' === $ext ? 'jpg' : $ext;
            }
        }

        return null;
    }

    private function detectMimeType(?string $headerContentType, string $content): ?string
    {
        if (\is_string($headerContentType) && str_contains($headerContentType, '/')) {
            return strtolower(trim(explode(';', $headerContentType)[0]));
        }

        if (\function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if (false !== $finfo) {
                $detected = finfo_buffer($finfo, $content);
                finfo_close($finfo);
                if (\is_string($detected) && str_contains($detected, '/')) {
                    return strtolower($detected);
                }
            }
        }

        return null;
    }
}
