<?php

declare(strict_types=1);

namespace App\Tests\TeamRecap;

use App\TeamRecap\TeamRecapGifSlot;
use PHPUnit\Framework\TestCase;

final class TeamRecapGifSlotTest extends TestCase
{
    public function testSubjectCodeForTeamPoints(): void
    {
        self::assertSame(TeamRecapGifSlot::SUBJECT_HOT, TeamRecapGifSlot::subjectCodeForTeamPoints(50));
        self::assertSame(TeamRecapGifSlot::SUBJECT_POSITIVE, TeamRecapGifSlot::subjectCodeForTeamPoints(1));
        self::assertSame(TeamRecapGifSlot::SUBJECT_NEUTRAL, TeamRecapGifSlot::subjectCodeForTeamPoints(0));
    }

    public function testJokerSlots(): void
    {
        self::assertSame('joker.double_equipe.useful', TeamRecapGifSlot::jokerUseful('double_equipe'));
        self::assertSame('joker.pique_points.not_useful', TeamRecapGifSlot::jokerNotUseful('pique_points'));
    }
}
