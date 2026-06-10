<?php

declare(strict_types=1);

namespace App\Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeImmutableType;

/**
 * Les coup d'envoi API-Football sont stockés en UTC dans MySQL (DATETIME sans fuseau).
 * Ce type évite de les interpréter comme heure locale quand PHP est en Europe/Paris.
 */
final class UtcDateTimeImmutableType extends DateTimeImmutableType
{
    private const string UTC = 'UTC';

    public function getName(): string
    {
        return 'utc_datetime_immutable';
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (!$value instanceof \DateTimeImmutable) {
            return parent::convertToDatabaseValue($value, $platform);
        }

        return $value->setTimezone($this->utcZone())->format($platform->getDateTimeFormatString());
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?\DateTimeImmutable
    {
        if (null === $value || $value instanceof \DateTimeImmutable) {
            return $value;
        }

        $parsed = \DateTimeImmutable::createFromFormat(
            $platform->getDateTimeFormatString(),
            $value,
            $this->utcZone(),
        );

        if (false !== $parsed) {
            return $parsed;
        }

        return parent::convertToPHPValue($value, $platform);
    }

    private function utcZone(): \DateTimeZone
    {
        static $zone = null;

        $zone ??= new \DateTimeZone(self::UTC);

        return $zone;
    }
}
