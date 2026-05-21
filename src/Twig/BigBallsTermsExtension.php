<?php

declare(strict_types=1);

namespace App\Twig;

use App\GameTerminology\BigBallsTerms;
use Twig\Attribute\AsTwigFunction;

final class BigBallsTermsExtension
{
    #[AsTwigFunction('bigballs_term')]
    public function term(string $key): string
    {
        return BigBallsTerms::get($key);
    }
}
