<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\ForumContentSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ForumContentSanitizerTest extends TestCase
{
    private ForumContentSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new ForumContentSanitizer();
    }

    public function testStripsScriptsAndUnsafeAttributes(): void
    {
        $html = '<p onclick="alert(1)">Salut</p><script>alert(1)</script>';
        $clean = $this->sanitizer->sanitize($html);

        self::assertStringContainsString('<p>Salut</p>', $clean);
        self::assertStringNotContainsString('script', $clean);
        self::assertStringNotContainsString('onclick', $clean);
    }

    public function testKeepsAllowedFormatting(): void
    {
        $html = '<p><strong>Gras</strong> et <em>italique</em></p><ul><li>Un</li></ul>';
        $clean = $this->sanitizer->sanitize($html);

        self::assertSame($html, $clean);
    }

    public function testSanitizesLinks(): void
    {
        $html = '<p><a href="https://example.com">OK</a> <a href="javascript:alert(1)">Bad</a></p>';
        $clean = $this->sanitizer->sanitize($html);

        self::assertStringContainsString('href="https://example.com"', $clean);
        self::assertStringContainsString('rel="noopener noreferrer"', $clean);
        self::assertStringNotContainsString('javascript:', $clean);
    }

    #[DataProvider('emptyProvider')]
    public function testDetectsEmptyContent(string $html, bool $empty): void
    {
        self::assertSame($empty, $this->sanitizer->isEffectivelyEmpty($html));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function emptyProvider(): iterable
    {
        yield 'blank' => ['', true];
        yield 'tags only' => ['<p><br></p>', true];
        yield 'text' => ['<p>Hello</p>', false];
    }
}
