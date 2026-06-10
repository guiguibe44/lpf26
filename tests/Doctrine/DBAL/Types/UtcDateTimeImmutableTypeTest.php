<?php

declare(strict_types=1);

namespace App\Tests\Doctrine\DBAL\Types;

use App\Doctrine\DBAL\Types\UtcDateTimeImmutableType;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use PHPUnit\Framework\TestCase;

final class UtcDateTimeImmutableTypeTest extends TestCase
{
    private UtcDateTimeImmutableType $type;

    private MySQLPlatform $platform;

    protected function setUp(): void
    {
        $this->type = new UtcDateTimeImmutableType();
        $this->platform = new MySQLPlatform();
    }

    public function testReadsDatabaseValueAsUtc(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('Europe/Paris');

        try {
            $value = $this->type->convertToPHPValue('2026-06-11 19:00:00', $this->platform);
            self::assertInstanceOf(\DateTimeImmutable::class, $value);
            self::assertSame('UTC', $value->getTimezone()->getName());
            self::assertSame(
                '21:00',
                $value->setTimezone(new \DateTimeZone('Europe/Paris'))->format('H:i'),
            );
        } finally {
            date_default_timezone_set($previous);
        }
    }

    public function testWritesParisKickoffAsUtcInDatabase(): void
    {
        $kickoffParis = new \DateTimeImmutable(
            '2026-06-11 21:00:00',
            new \DateTimeZone('Europe/Paris'),
        );

        self::assertSame(
            '2026-06-11 19:00:00',
            $this->type->convertToDatabaseValue($kickoffParis, $this->platform),
        );
    }
}
