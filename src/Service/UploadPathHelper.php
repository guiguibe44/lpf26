<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Chemins médias : en base = nom de fichier (ou URL distante), côté web = /uploads/{subdir}/….
 */
final class UploadPathHelper
{
    public static function publicPath(?string $stored, string $subdir): ?string
    {
        if (null === $stored || '' === $stored) {
            return null;
        }

        if (str_starts_with($stored, 'http://') || str_starts_with($stored, 'https://')) {
            return $stored;
        }

        $prefix = '/uploads/'.$subdir.'/';
        if (str_starts_with($stored, $prefix)) {
            return self::encodePublicUploadPath($stored);
        }

        if (str_starts_with($stored, '/')) {
            return self::encodePublicUploadPath($stored);
        }

        return $prefix.self::encodeUploadFilename($stored);
    }

    /**
     * Encode le nom de fichier pour une URL HTTP (espaces, virgules, etc.).
     */
    public static function encodeUploadFilename(string $filename): string
    {
        return rawurlencode($filename);
    }

    private static function encodePublicUploadPath(string $path): string
    {
        $lastSlash = strrpos($path, '/');
        if (false === $lastSlash) {
            return self::encodeUploadFilename($path);
        }

        $dir = substr($path, 0, $lastSlash + 1);
        $file = substr($path, $lastSlash + 1);

        return $dir.self::encodeUploadFilename($file);
    }

    /**
     * Valeur à persister : nom de fichier seul (compatible EasyAdmin ImageField).
     */
    public static function normalizeStored(?string $path, string $subdir): ?string
    {
        if (null === $path || '' === $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $prefix = '/uploads/'.$subdir.'/';
        if (str_starts_with($path, $prefix)) {
            return substr($path, \strlen($prefix));
        }

        if (str_starts_with($path, '/uploads/')) {
            return basename($path);
        }

        return ltrim($path, '/');
    }

    public static function isLocalUpload(?string $stored, string $subdir): bool
    {
        if (null === $stored || '' === $stored) {
            return false;
        }

        if (str_starts_with($stored, 'http://') || str_starts_with($stored, 'https://')) {
            return false;
        }

        if (str_starts_with($stored, '/uploads/'.$subdir.'/')) {
            return true;
        }

        return !str_contains($stored, '/');
    }
}
