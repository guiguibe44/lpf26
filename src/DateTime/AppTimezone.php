<?php

declare(strict_types=1);

namespace App\DateTime;

final class AppTimezone
{
    public const string ID = 'Europe/Paris';

    public static function zone(): \DateTimeZone
    {
        static $zone = null;

        $zone ??= new \DateTimeZone(self::ID);

        return $zone;
    }

    public static function toLocal(\DateTimeInterface $at): \DateTimeImmutable
    {
        $immutable = $at instanceof \DateTimeImmutable
            ? $at
            : \DateTimeImmutable::createFromInterface($at);

        return $immutable->setTimezone(self::zone());
    }

    public static function todayKey(?\DateTimeImmutable $now = null): string
    {
        $now ??= new \DateTimeImmutable();

        return self::toLocal($now)->format('Y-m-d');
    }
}
