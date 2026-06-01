<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\KnockoutFixtureLabelResolver;
use PHPUnit\Framework\TestCase;

final class KnockoutFixtureLabelResolverTest extends TestCase
{
    private KnockoutFixtureLabelResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new KnockoutFixtureLabelResolver();
    }

    public function testResolveGroupPosition(): void
    {
        self::assertSame('1er du groupe A', $this->resolver->resolve('1A'));
        self::assertSame('2e du groupe F', $this->resolver->resolve('2F'));
    }

    public function testResolveBestThird(): void
    {
        self::assertSame('Meilleur 3e (poules A, B, C, D, F)', $this->resolver->resolve('3ABCDF'));
    }

    public function testResolveWinnerAndLoser(): void
    {
        self::assertSame('Vainqueur du match 89', $this->resolver->resolve('W89'));
        self::assertSame('Perdant du match 101', $this->resolver->resolve('RU101'));
    }
}
