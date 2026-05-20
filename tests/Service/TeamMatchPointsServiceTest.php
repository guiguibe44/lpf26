<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Country;
use App\Entity\GameMatch;
use App\Entity\Pronostic;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Repository\PronosticRepository;
use App\Service\TeamMatchPointsService;
use PHPUnit\Framework\TestCase;

final class TeamMatchPointsServiceTest extends TestCase
{
    public function testBuildPointsByMatchIdSumsPronosticsAndGoals(): void
    {
        $player = (new User())->setEmail('a@test.fr');
        $reflectionUser = new \ReflectionClass(User::class);
        $reflectionUser->getProperty('id')->setValue($player, 10);

        $team = (new Team())->setName('Équipe A');
        $reflectionTeam = new \ReflectionClass(Team::class);
        $reflectionTeam->getProperty('id')->setValue($team, 1);

        $member = (new TeamMember())->setTeam($team)->setPlayer($player);
        $team->getMembers()->add($member);

        $match = new GameMatch();
        $reflectionMatch = new \ReflectionClass(GameMatch::class);
        $reflectionMatch->getProperty('id')->setValue($match, 7);
        $match->setScoreDomicile(1)->setScoreExterieur(0);

        $pronostic = (new Pronostic())
            ->setJoueur($player)
            ->setMatch($match)
            ->setScoreDomicile(1)
            ->setScoreExterieur(0)
            ->setPoints(12.0)
            ->setPointsEquipe(15.0);

        $pronosticRepository = $this->createMock(PronosticRepository::class);
        $pronosticRepository
            ->method('sumContributionPointsByMatchForPlayers')
            ->with([10], [$match])
            ->willReturn([7 => 15.0]);

        $service = new TeamMatchPointsService($pronosticRepository);

        $totals = $service->buildPointsByMatchIdForTeam($team, [$match], [
            7 => [
                ['buteur_id' => 99, 'points' => 8],
            ],
        ]);

        self::assertSame([7 => 15], $totals);
    }
}
