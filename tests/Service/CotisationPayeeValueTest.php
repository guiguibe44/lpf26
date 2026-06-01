<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\CotisationPayeeValue;
use PHPUnit\Framework\TestCase;

final class CotisationPayeeValueTest extends TestCase
{
    public function testIsPaidAcceptsBoolAndTinyInt(): void
    {
        self::assertTrue(CotisationPayeeValue::isPaid(true));
        self::assertTrue(CotisationPayeeValue::isPaid(1));
        self::assertTrue(CotisationPayeeValue::isPaid('1'));
        self::assertFalse(CotisationPayeeValue::isPaid(false));
        self::assertFalse(CotisationPayeeValue::isPaid(0));
        self::assertFalse(CotisationPayeeValue::isPaid('0'));
        self::assertFalse(CotisationPayeeValue::isPaid(null));
    }

    public function testBecamePaid(): void
    {
        self::assertTrue(CotisationPayeeValue::becamePaid(false, true));
        self::assertTrue(CotisationPayeeValue::becamePaid(0, 1));
        self::assertFalse(CotisationPayeeValue::becamePaid(1, 1));
        self::assertFalse(CotisationPayeeValue::becamePaid(true, true));
        self::assertFalse(CotisationPayeeValue::becamePaid(1, true));
    }
}
