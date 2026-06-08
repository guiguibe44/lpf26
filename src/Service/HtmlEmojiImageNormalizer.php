<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Remplace les emojis insérés comme images (ex. sélecteur macOS dans contenteditable)
 * par leur caractère Unicode issu de l’attribut alt.
 */
final class HtmlEmojiImageNormalizer
{
    public static function normalize(string $html): string
    {
        if (!str_contains($html, '<img')) {
            return $html;
        }

        return preg_replace_callback(
            '/<img\b[^>]*\balt=(["\'])([^"\']*)\1[^>]*>/iu',
            static function (array $matches): string {
                $alt = html_entity_decode($matches[2], \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
                if ('' === $alt || 1 !== preg_match('/\p{Extended_Pictographic}/u', $alt)) {
                    return '';
                }

                return $alt;
            },
            $html,
        ) ?? $html;
    }
}
