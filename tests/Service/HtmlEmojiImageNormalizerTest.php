<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\HtmlEmojiImageNormalizer;
use PHPUnit\Framework\TestCase;

final class HtmlEmojiImageNormalizerTest extends TestCase
{
    public function testConvertsEmojiImageAltToUnicode(): void
    {
        $html = '<p>Salut <img alt="👍" src="x"></p>';

        self::assertSame('<p>Salut 👍</p>', HtmlEmojiImageNormalizer::normalize($html));
    }

    public function testRemovesNonEmojiImages(): void
    {
        $html = '<p><img alt="logo" src="/logo.png"></p>';

        self::assertSame('<p></p>', HtmlEmojiImageNormalizer::normalize($html));
    }
}
