<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Nettoie le HTML saisi via l’éditeur du forum (balises limitées, liens http(s) uniquement).
 */
final class ForumContentSanitizer
{
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><u><a><ul><ol><li><blockquote><div><span>';

    public function sanitize(string $html): string
    {
        $html = trim($html);
        if ('' === $html) {
            return '';
        }

        $html = HtmlEmojiImageNormalizer::normalize($html);
        $html = strip_tags($html, self::ALLOWED_TAGS);
        $html = preg_replace('/\s*on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $html) ?? $html;
        $html = preg_replace('/\s*style\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $html) ?? $html;

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $wrapped = '<?xml encoding="UTF-8"><div>'.$html.'</div>';
        $dom->loadHTML($wrapped, \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        foreach ($dom->getElementsByTagName('a') as $anchor) {
            if (!$anchor instanceof \DOMElement) {
                continue;
            }
            $href = trim((string) $anchor->getAttribute('href'));
            if (!$this->isAllowedHref($href)) {
                $anchor->removeAttribute('href');
                $anchor->removeAttribute('target');
                $anchor->removeAttribute('rel');
                continue;
            }
            $anchor->setAttribute('href', $href);
            $anchor->setAttribute('target', '_blank');
            $anchor->setAttribute('rel', 'noopener noreferrer');
        }

        foreach ($dom->getElementsByTagName('span') as $span) {
            if (!$span instanceof \DOMElement) {
                continue;
            }
            if (!str_contains($span->getAttribute('class'), 'forum-mention')) {
                $this->unwrapElement($span);
                continue;
            }
            $userId = (int) $span->getAttribute('data-user-id');
            if ($userId <= 0) {
                $this->unwrapElement($span);
                continue;
            }
            $span->removeAttribute('style');
            $span->setAttribute('class', 'forum-mention');
            $span->setAttribute('data-user-id', (string) $userId);
            $span->setAttribute('contenteditable', 'false');
        }

        $root = $dom->documentElement;
        if (null === $root) {
            return '';
        }

        $inner = '';
        foreach ($root->childNodes as $child) {
            $inner .= $dom->saveHTML($child);
        }

        return trim($inner);
    }

    public function isEffectivelyEmpty(string $html): bool
    {
        $html = HtmlEmojiImageNormalizer::normalize($html);
        $text = trim(html_entity_decode(strip_tags($html), \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));
        $withoutWhitespace = preg_replace('/\s+/u', '', $text) ?? '';

        if ('' !== $withoutWhitespace) {
            return false;
        }

        return 1 !== preg_match('/\p{Extended_Pictographic}/u', $text);
    }

    private function unwrapElement(\DOMElement $element): void
    {
        $parent = $element->parentNode;
        if (null === $parent) {
            return;
        }
        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
    }

    private function isAllowedHref(string $href): bool
    {
        if ('' === $href) {
            return false;
        }

        return 1 === preg_match('#^https?://#i', $href);
    }
}
