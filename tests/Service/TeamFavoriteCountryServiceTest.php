<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Country;
use App\Entity\GameMatch;
use App\Entity\Joker;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\JokerRepository;
use App\Service\CompetitionStatus;
use App\Service\TeamFavoriteCountryService;
use PHPUnit\Framework\TestCase;

final class TeamFavoriteCountryServiceTest extends TestCase
{
    private function createService(
        string $competitionStartAt,
        ?JokerRepository $jokerRepository = null,
    ): TeamFavoriteCountryService {
        return new TeamFavoriteCountryService(
            new CompetitionStatus($competitionStartAt),
            $jokerRepository ?? $this->createMock(JokerRepository::class),
        );
    }

    public function testCanManageBeforeCompetitionStart(): void
    {
        $service = $this->createService('2099-01-01T12:00:00+00:00');
        $user = (new User())->setCotisationPayee(true);

        self::assertTrue($service->canManageFavoriteCountry($user));
    }

    public function testCannotManageAfterCompetitionStart(): void
    {
        $service = $this->createService('2000-01-01T12:00:00+00:00');
        $user = (new User())->setCotisationPayee(true);

        self::assertFalse($service->canManageFavoriteCountry($user));
    }

    public function testBuildAccountStateShowsCountryWhenLocked(): void
    {
        $service = $this->createService('2000-01-01T12:00:00+00:00');
        $team = (new Team())->setName('Test')->setFavoriteCountry((new Country())->setNom('Mexique'));
        $user = (new User())->setCotisationPayee(true);

        $state = $service->buildAccountState($team, $user);

        self::assertTrue($state['locked']);
        self::assertSame('Mexique', $state['country_name']);
        self::assertFalse($state['can_manage']);
    }

    public function testBuildMatchCardHighlightListsGroupMatches(): void
    {
        $mexique = (new Country())->setNom('Mexique');
        $reflection = new \ReflectionClass(Country::class);
        $idProp = $reflection->getProperty('id');
        $idProp->setValue($mexique, 42);

        $team = (new Team())->setName('Test')->setFavoriteCountry($mexique);
        $match = new GameMatch();

        $joker = (new Joker())
            ->setCode(Joker::CODE_EQUIPE_FAVORITE)
            ->setName('Équipe favorite')
            ->setImage('/uploads/jokers/favorite.png');

        $jokerRepo = $this->createMock(JokerRepository::class);
        $jokerRepo->method('findAllOrdered')->willReturn([$joker]);

        $matchReflection = new \ReflectionClass(GameMatch::class);
        $matchIdProp = $matchReflection->getProperty('id');
        $matchIdProp->setValue($match, 7);
        $match->setPaysDomicile($mexique);
        $match->setPhase('Group A');

        $service = $this->createService('2099-01-01T12:00:00+00:00', $jokerRepo);
        $highlight = $service->buildMatchCardHighlight($team, [$match]);

        self::assertNotNull($highlight);
        self::assertSame(42, $highlight['country_id']);
        self::assertSame('Mexique', $highlight['country_name']);
        self::assertTrue($highlight['match_ids'][7] ?? false);
        self::assertSame('/uploads/jokers/favorite.png', $highlight['joker_image']);
    }
}
