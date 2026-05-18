<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Extrait les identifiants joueurs depuis les spans @mention du HTML forum.
 */
final class ForumMentionParser
{
    /**
     * @return list<int>
     */
    public function extractMentionedUserIds(string $html): array
    {
        $html = trim($html);
        if ('' === $html) {
            return [];
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div>'.$html.'</div>',
            \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $ids = [];
        foreach ($dom->getElementsByTagName('span') as $span) {
            if (!$span instanceof \DOMElement) {
                continue;
            }
            if (!str_contains($span->getAttribute('class'), 'forum-mention')) {
                continue;
            }
            $userId = (int) $span->getAttribute('data-user-id');
            if ($userId > 0) {
                $ids[] = $userId;
            }
        }

        return array_values(array_unique($ids));
    }
}
