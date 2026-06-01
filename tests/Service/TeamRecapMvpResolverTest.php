<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\GameMatch;
use App\Entity\Pronostic;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Service\TeamRecapMvpResolver;
use PHPUnit\Framework\TestCase;

final class TeamRecapMvpResolverTest extends TestCase
{
    public function testRanksByPointsThenExactScores(): void
    {
        $match = new GameMatch();
        (new \ReflectionProperty(GameMatch::class, 'id'))->setValue($match, 1);

        $userA = (new User())->setEmail('a@test.fr');
        (new \ReflectionProperty(User::class, 'id'))->setValue($userA, 10);

        $userB = (new User())->setEmail('b@test.fr');
        (new \ReflectionProperty(User::class, 'id'))->setValue($userB, 20);

        $memberA = (new TeamMember())->setPlayer($userA)->setNickname('Alpha');
        $memberB = (new TeamMember())->setPlayer($userB)->setNickname('Bravo');

        $pronoA = (new Pronostic())->setJoueur($userA)->setMatch($match);
        $pronoA->setPoints(10)->setPointsBase(10);
        $pronoB = (new Pronostic())->setJoueur($userB)->setMatch($match);
        $pronoB->setPoints(30)->setPointsBase(30);

        $resolver = new TeamRecapMvpResolver();
        $ranked = $resolver->rankMembers(
            [$memberA, $memberB],
            [$match],
            [1 => [10 => $pronoA, 20 => $pronoB]],
            [],
        );

        self::assertSame('Bravo', $ranked[0]['nickname']);
        self::assertSame(30, $ranked[0]['points']);
        self::assertSame('Alpha', $ranked[1]['nickname']);
    }
}
