<?php

declare(strict_types=1);

namespace App\Enum;

enum EditorialAuthorCountry: string
{
    case USA = 'USA';
    case Canada = 'Canada';
    case Mexique = 'Mexique';

    /**
     * @return array<string, string>
     */
    public static function choices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->label()] = $case->value;
        }

        return $choices;
    }

    public function label(): string
    {
        return $this->value;
    }
}
