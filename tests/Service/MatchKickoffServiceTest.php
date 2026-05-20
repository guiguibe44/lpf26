<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Country;
use App\Entity\GameMatch;
use App\Repository\GameMatchRepository;
use App\Repository\PronosticRepository;
use App\Repository\UserRepository;
use App\Service\DefaultPronosticService;
use App\Service\MatchKickoffService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class MatchKickoffServiceTest extends TestCase
{
    public function testApplyKickoffSetsLiveAndScoresWhenKickoffPassed(): void
    {
        $match = $this->scheduledMatch(new \DateTimeImmutable('-5 minutes'));
        $this->createService()->applyKickoff($match);

        self::assertSame('LIVE', $match->getStatut());
        self::assertSame(0, $match->getScoreDomicile());
        self::assertSame(0, $match->getScoreExterieur());
        self::assertSame(0, $match->getLiveElapsedMinute());
    }

    public function testApplyKickoffIgnoresFutureKickoff(): void
    {
        $match = $this->scheduledMatch(new \DateTimeImmutable('+1 hour'));
        $this->createService()->applyKickoff($match);

        self::assertSame('SCHEDULED', $match->getStatut());
        self::assertNull($match->getScoreDomicile());
    }

    private function createService(): MatchKickoffService
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findPlayersWithoutPronosticForMatch')->willReturn([]);

        return new MatchKickoffService(
            $this->createStub(GameMatchRepository::class),
            $userRepository,
            new DefaultPronosticService(
                $this->createStub(GameMatchRepository::class),
                $this->createStub(PronosticRepository::class),
                $userRepository,
                $this->createStub(EntityManagerInterface::class),
            ),
            $this->createStub(EntityManagerInterface::class),
        );
    }

    private function scheduledMatch(\DateTimeImmutable $dateHeure): GameMatch
    {
        $home = (new Country())->setNom('France');
        $away = (new Country())->setNom('Allemagne');

        return (new GameMatch())
            ->setPaysDomicile($home)
            ->setPaysExterieur($away)
            ->setDateHeure($dateHeure)
            ->setStatut('SCHEDULED');
    }
}
