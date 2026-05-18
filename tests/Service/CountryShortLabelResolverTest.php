<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Country;
use App\Service\CountryShortLabelResolver;
use PHPUnit\Framework\TestCase;

final class CountryShortLabelResolverTest extends TestCase
{
    private CountryShortLabelResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new CountryShortLabelResolver();
    }

    public function testResolvesKnownCountryCode(): void
    {
        $country = (new Country())->setNom('France');

        self::assertSame('FRA', $this->resolver->resolve($country));
    }

    public function testResolvesCodeFromFlagFilename(): void
    {
        $country = (new Country())
            ->setNom('Unknownland')
            ->setDrapeau('England-cf35ffaf4da9.png');

        self::assertSame('ENG', $this->resolver->resolve($country));
    }

    public function testReturnsDashWhenCountryMissing(): void
    {
        self::assertSame('-', $this->resolver->resolve(null));
    }
}
