<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Country;
use App\Entity\GameMatch;
use App\Service\MatchReminderMessageFactory;
use PHPUnit\Framework\TestCase;

final class MatchReminderMessageFactoryTest extends TestCase
{
    private MatchReminderMessageFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new MatchReminderMessageFactory();
    }

    public function testBuildForMatchdayGroupsMultipleMatches(): void
    {
        [$title, $body] = $this->factory->buildForMatchday([
            $this->match('France', 'Allemagne', '2026-06-15 15:00:00'),
            $this->match('Espagne', 'Italie', '2026-06-15 20:00:00'),
        ]);

        self::assertSame('Pronostics à faire', $title);
        self::assertStringContainsString('matchs du 15/06/2026', $body);
        self::assertStringContainsString('France — Allemagne (coup d\'envoi à 15h00)', $body);
        self::assertStringContainsString('Espagne — Italie (coup d\'envoi à 20h00)', $body);
    }

    public function testBuildForMatchKeepsSingleMatchWording(): void
    {
        $match = $this->match('France', 'Allemagne', '2026-06-15 15:00:00');
        $kickoff = $match->getDateHeure();
        \assert($kickoff instanceof \DateTimeImmutable);

        [, $body] = $this->factory->buildForMatch($match, $kickoff);

        self::assertStringContainsString('France — Allemagne (coup d\'envoi à 15h00)', $body);
        self::assertStringContainsString('tu n\'as pas encore pronostiqué', $body);
    }

    private function match(string $home, string $away, string $kickoff): GameMatch
    {
        return (new GameMatch())
            ->setPaysDomicile((new Country())->setNom($home))
            ->setPaysExterieur((new Country())->setNom($away))
            ->setDateHeure(new \DateTimeImmutable($kickoff, new \DateTimeZone('Europe/Paris')))
            ->setStatut('SCHEDULED');
    }
}
