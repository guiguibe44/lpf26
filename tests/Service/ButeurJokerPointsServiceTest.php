<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Buteur;
use App\Entity\But;
use App\Entity\Country;
use App\Entity\GameMatch;
use App\Entity\Joker;
use App\Entity\Team;
use App\Entity\TeamJokerUsage;
use App\Entity\User;
use App\Repository\ButRepository;
use App\Repository\TeamJokerUsageRepository;
use App\Service\ButeurJokerPointsService;
use PHPUnit\Framework\TestCase;

final class ButeurJokerPointsServiceTest extends TestCase
{
    public function testMatchEligibleWhenButeurCountryPlays(): void
    {
        $france = (new Country())->setNom('France');
        $germany = (new Country())->setNom('Allemagne');
        $this->setId($france, 1);
        $this->setId($germany, 2);

        $buteur = (new Buteur())->setNom('A')->setPrenom('B')->setPays($france);
        $player = (new User())->setButeurChoisi($buteur);

        $team = new Team();
        $team->addMember((new \App\Entity\TeamMember())->setPlayer($player));

        $match = (new GameMatch())
            ->setPaysDomicile($france)
            ->setPaysExterieur($germany)
            ->setDateHeure(new \DateTimeImmutable());

        $service = new ButeurJokerPointsService(
            $this->createMock(TeamJokerUsageRepository::class),
            $this->createMock(ButRepository::class),
        );

        self::assertTrue($service->isMatchEligibleForDoubleButeurJoker($team, $match));
    }

    public function testMatchNotEligibleWhenNoButeurCountryInFixture(): void
    {
        $france = (new Country())->setNom('France');
        $brazil = (new Country())->setNom('Brésil');
        $this->setId($france, 1);
        $this->setId($brazil, 3);

        $buteur = (new Buteur())->setNom('A')->setPrenom('B')->setPays($france);
        $player = (new User())->setButeurChoisi($buteur);

        $team = new Team();
        $team->addMember((new \App\Entity\TeamMember())->setPlayer($player));

        $match = (new GameMatch())
            ->setPaysDomicile((new Country())->setNom('Espagne'))
            ->setPaysExterieur($brazil)
            ->setDateHeure(new \DateTimeImmutable());
        $this->setId($match->getPaysDomicile(), 10);
        $this->setId($match->getPaysExterieur(), 3);

        $service = new ButeurJokerPointsService(
            $this->createMock(TeamJokerUsageRepository::class),
            $this->createMock(ButRepository::class),
        );

        self::assertFalse($service->isMatchEligibleForDoubleButeurJoker($team, $match));
    }

    public function testSumEffectivePointsDoublesGoalsOnJokerMatch(): void
    {
        $team = new Team();
        $this->setId($team, 5);

        $buteur = new Buteur();
        $this->setId($buteur, 7);

        $matchJoker = new GameMatch();
        $this->setId($matchJoker, 100);
        $matchOther = new GameMatch();
        $this->setId($matchOther, 200);

        $butJoker = (new But())->setButeur($buteur)->setMatchRef($matchJoker)->setPointsAttribues(6);
        $butOther = (new But())->setButeur($buteur)->setMatchRef($matchOther)->setPointsAttribues(4);

        $usageRepo = $this->createMock(TeamJokerUsageRepository::class);
        $usageRepo->method('findByTeamOrdered')->willReturn([
            (new TeamJokerUsage())
                ->setTeam($team)
                ->setMatch($matchJoker)
                ->setJoker((new Joker())->setCode(Joker::CODE_DOUBLE_BUTEUR)),
        ]);

        $butRepo = $this->createMock(ButRepository::class);
        $butRepo->method('findForButeurOrderedByMatch')->with($buteur)->willReturn([$butJoker, $butOther]);
        $butRepo->method('sumPointsAttribuesForButeur')->with($buteur)->willReturn(10);

        $service = new ButeurJokerPointsService($usageRepo, $butRepo);

        self::assertSame(16.0, $service->sumEffectivePointsForButeur($team, $buteur));
    }

    public function testSumEffectivePointsInvertsGoalsOnTargetedMatch(): void
    {
        $team = new Team();
        $this->setId($team, 5);

        $buteur = new Buteur();
        $this->setId($buteur, 7);

        $matchInvert = new GameMatch();
        $this->setId($matchInvert, 100);
        $matchOther = new GameMatch();
        $this->setId($matchOther, 200);

        $butInvert = (new But())->setButeur($buteur)->setMatchRef($matchInvert)->setPointsAttribues(6);
        $butOther = (new But())->setButeur($buteur)->setMatchRef($matchOther)->setPointsAttribues(4);

        $usageRepo = $this->createMock(TeamJokerUsageRepository::class);
        $usageRepo->method('findByTeamOrdered')->willReturn([]);
        $usageRepo->method('findInvertButeurMatchIdsForTargetTeam')->with($team)->willReturn([100]);

        $butRepo = $this->createMock(ButRepository::class);
        $butRepo->method('findForButeurOrderedByMatch')->with($buteur)->willReturn([$butInvert, $butOther]);
        $butRepo->method('sumPointsAttribuesForButeur')->with($buteur)->willReturn(10);

        $service = new ButeurJokerPointsService($usageRepo, $butRepo);

        self::assertSame(-2.0, $service->sumEffectivePointsForButeur($team, $buteur));
    }

    public function testInvertAndDoubleCombineOnSameMatch(): void
    {
        $team = new Team();
        $this->setId($team, 5);

        $buteur = new Buteur();
        $this->setId($buteur, 7);

        $match = new GameMatch();
        $this->setId($match, 100);

        $but = (new But())->setButeur($buteur)->setMatchRef($match)->setPointsAttribues(5);

        $usageRepo = $this->createMock(TeamJokerUsageRepository::class);
        $usageRepo->method('findByTeamOrdered')->willReturn([
            (new TeamJokerUsage())
                ->setTeam($team)
                ->setMatch($match)
                ->setJoker((new Joker())->setCode(Joker::CODE_DOUBLE_BUTEUR)),
        ]);
        $usageRepo->method('findInvertButeurMatchIdsForTargetTeam')->with($team)->willReturn([100]);

        $butRepo = $this->createMock(ButRepository::class);
        $butRepo->method('findForButeurOrderedByMatch')->with($buteur)->willReturn([$but]);
        $butRepo->method('sumPointsAttribuesForButeur')->with($buteur)->willReturn(5);

        $service = new ButeurJokerPointsService($usageRepo, $butRepo);

        self::assertSame(-10.0, $service->sumEffectivePointsForButeur($team, $buteur));
    }

    private function setId(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty($entity, 'id');
        $ref->setAccessible(true);
        $ref->setValue($entity, $id);
    }
}
