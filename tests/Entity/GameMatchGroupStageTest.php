<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Country;
use App\Entity\GameMatch;
use PHPUnit\Framework\TestCase;

final class GameMatchGroupStageTest extends TestCase
{
    public function testExtractGroupLetterFromFrenchPhase(): void
    {
        self::assertSame('A', GameMatch::extractGroupStandingLetter('Groupe A'));
        self::assertSame('B', GameMatch::extractGroupStandingLetter('Phase Groupe B - J2'));
    }

    public function testIsGroupStageMatchFromCountryGroups(): void
    {
        $home = (new Country())->setNom('Mexique')->setGroupe('E');
        $away = (new Country())->setNom('Pologne')->setGroupe('E');
        $match = (new GameMatch())
            ->setPaysDomicile($home)
            ->setPaysExterieur($away)
            ->setPhase('Phase de groupes');

        self::assertTrue($match->isGroupStageMatch());
    }
}
