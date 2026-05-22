<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Country;
use App\Service\GroupKnockoutQualificationAnalyzer;
use PHPUnit\Framework\TestCase;

final class GroupKnockoutQualificationAnalyzerTest extends TestCase
{
    public function testQualifiesTopTwoAndBarsFourthWhenGroupFinished(): void
    {
        $analyzer = new GroupKnockoutQualificationAnalyzer();
        $rows = [
            $this->row('France', 3, 7),
            $this->row('Senegal', 3, 4),
            $this->row('Norway', 3, 3),
            $this->row('Iraq', 3, 1),
        ];

        $result = $analyzer->enrich(['I' => $rows])['I'];

        self::assertSame(GroupKnockoutQualificationAnalyzer::STATUS_QUALIFIED_DIRECT, $result[0]['knockout_status']);
        self::assertSame(GroupKnockoutQualificationAnalyzer::STATUS_QUALIFIED_DIRECT, $result[1]['knockout_status']);
        self::assertSame(GroupKnockoutQualificationAnalyzer::STATUS_QUALIFIED_THIRD, $result[2]['knockout_status']);
        self::assertSame(GroupKnockoutQualificationAnalyzer::STATUS_ELIMINATED, $result[3]['knockout_status']);
    }

    public function testEliminatesTeamLiveWhenCannotCatchTopTwo(): void
    {
        $analyzer = new GroupKnockoutQualificationAnalyzer();
        $rows = [
            $this->row('France', 2, 6),
            $this->row('Senegal', 2, 4),
            $this->row('Norway', 1, 1),
            $this->row('Iraq', 1, 0),
        ];

        $result = $analyzer->enrich(['I' => $rows])['I'];

        self::assertSame(GroupKnockoutQualificationAnalyzer::STATUS_LIVE, $result[0]['knockout_status']);
        self::assertSame(GroupKnockoutQualificationAnalyzer::STATUS_LIVE, $result[1]['knockout_status']);
        self::assertSame(GroupKnockoutQualificationAnalyzer::STATUS_LIVE, $result[2]['knockout_status']);
        self::assertSame(GroupKnockoutQualificationAnalyzer::STATUS_ELIMINATED, $result[3]['knockout_status']);
    }

    public function testDoesNotBarUntilEveryTeamHasPlayedOnce(): void
    {
        $analyzer = new GroupKnockoutQualificationAnalyzer();
        $rows = [
            $this->row('France', 2, 6),
            $this->row('Senegal', 2, 4),
            $this->row('Norway', 1, 1),
            $this->row('Iraq', 0, 0),
        ];

        $result = $analyzer->enrich(['I' => $rows])['I'];

        self::assertSame(GroupKnockoutQualificationAnalyzer::STATUS_LIVE, $result[3]['knockout_status']);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $name, int $joues, int $points): array
    {
        $country = (new Country())->setNom($name);

        return [
            'country' => $country,
            'joues' => $joues,
            'victoires' => 0,
            'nuls' => 0,
            'defaites' => 0,
            'bp' => 0,
            'bc' => 0,
            'diff' => 0,
            'points' => $points,
        ];
    }
}
