<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\TeamMatchPointsTierResolver;
use PHPUnit\Framework\TestCase;

final class TeamMatchPointsTierResolverTest extends TestCase
{
    private TeamMatchPointsTierResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new TeamMatchPointsTierResolver();
    }

    public function testResolveTier(): void
    {
        self::assertSame('negative', $this->resolver->resolveTier(-12));
        self::assertSame('zero', $this->resolver->resolveTier(0));
        self::assertSame('low', $this->resolver->resolveTier(8));
        self::assertSame('good', $this->resolver->resolveTier(24));
        self::assertSame('strong', $this->resolver->resolveTier(48));
        self::assertSame('high', $this->resolver->resolveTier(120));
    }
}
