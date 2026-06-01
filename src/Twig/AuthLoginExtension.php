<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Fonds et citations colonne auth (/images/login, par pays).
 */
final class AuthLoginExtension extends AbstractExtension
{
    private const COUNTRIES = ['canada', 'mexique', 'usa'];

    /** @var array<string, list<string>> */
    private const QUOTES = [
        'canada' => [
            'Double-double, eh?',
            'Keep your stick on the ice.',
            "It's a beaut, eh?",
            'Bonjour-hi!',
            'Lâche pas la patate.',
            "C'est l'fun en masse.",
            'Sorry, not sorry.',
            'Tu connais le puck, là?',
        ],
        'mexique' => [
            '¡Qué onda!',
            'Échale ganas.',
            'Ni modo, así es esto.',
            'Más vale tarde que nunca.',
            'De aquí pal real.',
            'Ánimo, compa.',
            'Pa\' delante, siempre.',
            'Hoy se juega con el corazón.',
        ],
        'usa' => [
            "This ain't my first rodeo.",
            "Ball don't lie.",
            'Knock it out of the park.',
            'Go big or go home.',
            'We came to play.',
            'Leave it all on the field.',
            'Game on.',
            'No days off.',
        ],
    ];

    public function getFunctions(): array
    {
        return [
            new TwigFunction('auth_login_backgrounds', $this->pickBackgrounds(...)),
        ];
    }

    /**
     * @return array{
     *     country: string,
     *     light: string,
     *     dark: string,
     *     light_webp: string,
     *     dark_webp: string,
     *     quote: string
     * }
     */
    public function pickBackgrounds(): array
    {
        $country = self::COUNTRIES[array_rand(self::COUNTRIES)];

        return [
            'country' => $country,
            'light' => 'images/login/'.$country.'-light.jpg',
            'dark' => 'images/login/'.$country.'-dark.jpg',
            'light_webp' => 'images/login/'.$country.'-light.webp',
            'dark_webp' => 'images/login/'.$country.'-dark.webp',
            'quote' => $this->pickQuote($country),
        ];
    }

    private function pickQuote(string $country): string
    {
        $quotes = self::QUOTES[$country] ?? self::QUOTES['usa'];
        $index = array_rand($quotes);

        return $quotes[$index];
    }
}
