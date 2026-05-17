<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\GameMatch;
use App\Entity\Pronostic;
use App\Entity\User;
use App\Repository\TeamJokerUsageRepository;
use App\Service\JokerCollectePointsService;
use PHPUnit\Framework\TestCase;

final class JokerCollectePointsServiceTest extends TestCase
{
    public function testLevyTakesTenPercentFromOtherTeamsRounded(): void
    {
        $match = new GameMatch();
        (new \ReflectionProperty($match, 'id'))->setValue($match, 1);

        $pronoA = $this->pronostic(1, 1, 20.0);
        $pronoB = $this->pronostic(2, 2, 35.0);
        $pronoC = $this->pronostic(3, 3, 10.0);

        $repo = $this->createMock(TeamJokerUsageRepository::class);
        $repo->method('findCollecteTeamIdsForMatch')->willReturn([3]);

        $service = new JokerCollectePointsService($repo);
        $service->applyToPronostics($match, [$pronoA, $pronoB, $pronoC], [1 => 1, 2 => 2, 3 => 3]);

        self::assertSame(18.0, $pronoA->getPointsEquipe());
        self::assertSame(31.0, $pronoB->getPointsEquipe());
        self::assertSame(16.0, $pronoC->getPointsEquipe());
    }

    public function testNoLevyWhenNoCollectorOnMatch(): void
    {
        $match = new GameMatch();
        $prono = $this->pronostic(1, 1, 12.0);
        $prono->setPointsEquipe(12.0);

        $repo = $this->createMock(TeamJokerUsageRepository::class);
        $repo->method('findCollecteTeamIdsForMatch')->willReturn([]);

        $service = new JokerCollectePointsService($repo);
        $service->applyToPronostics($match, [$prono], [1 => 1]);

        self::assertSame(12.0, $prono->getPointsEquipe());
    }

    private function pronostic(int $id, int $playerId, float $teamPoints): Pronostic
    {
        $user = (new User())->setEmail('u'.$playerId.'@t.local');
        (new \ReflectionProperty($user, 'id'))->setValue($user, $playerId);

        $pronostic = (new Pronostic())
            ->setMatch(new GameMatch())
            ->setJoueur($user)
            ->setScoreDomicile(1)
            ->setScoreExterieur(0)
            ->setPoints($teamPoints)
            ->setPointsEquipe($teamPoints);
        (new \ReflectionProperty($pronostic, 'id'))->setValue($pronostic, $id);

        return $pronostic;
    }
}
