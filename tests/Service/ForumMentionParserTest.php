<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\ForumMentionParser;
use PHPUnit\Framework\TestCase;

final class ForumMentionParserTest extends TestCase
{
    public function testExtractsUserIdsFromMentionSpans(): void
    {
        $parser = new ForumMentionParser();
        $html = '<p>Salut <span class="forum-mention" data-user-id="12" contenteditable="false">@Zizou</span> !</p>';

        self::assertSame([12], $parser->extractMentionedUserIds($html));
    }

    public function testIgnoresInvalidMentions(): void
    {
        $parser = new ForumMentionParser();
        $html = '<p><span class="other" data-user-id="5">nope</span></p>';

        self::assertSame([], $parser->extractMentionedUserIds($html));
    }
}
