<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Repository\TeamRecapGifRepository;
use App\Service\TeamRecapGifPicker;
use App\Service\TeamRecapGifUrlBuilder;
use App\TeamRecap\TeamRecapGifSlot;
use PHPUnit\Framework\TestCase;

final class TeamRecapGifPickerTest extends TestCase
{
    public function testPickReturnsNullWhenNoPaths(): void
    {
        $repo = $this->createMock(TeamRecapGifRepository::class);
        $repo->method('findActivePathsBySlot')->willReturn([]);

        $picker = new TeamRecapGifPicker($repo, new TeamRecapGifUrlBuilder('https://lpf26.test'));

        self::assertNull($picker->pickRandomAbsoluteUrl(TeamRecapGifSlot::SUBJECT_HOT));
    }

    public function testPickSelectsAmongConfiguredPaths(): void
    {
        $repo = $this->createMock(TeamRecapGifRepository::class);
        $repo->method('findActivePathsBySlot')
            ->with(TeamRecapGifSlot::SUBJECT_POSITIVE)
            ->willReturn([
                '/uploads/recap-email/a.gif',
                '/uploads/recap-email/b.gif',
            ]);

        $picker = new TeamRecapGifPicker($repo, new TeamRecapGifUrlBuilder('https://lpf26.test'));

        $url = $picker->pickRandomAbsoluteUrl(TeamRecapGifSlot::SUBJECT_POSITIVE);
        self::assertTrue(\in_array($url, [
            'https://lpf26.test/uploads/recap-email/a.gif',
            'https://lpf26.test/uploads/recap-email/b.gif',
        ], true));
    }
}
