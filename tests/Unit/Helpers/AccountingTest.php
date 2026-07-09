<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\Accounting;
use PHPUnit\Framework\TestCase;

class AccountingTest extends TestCase
{
    public function testAccountingDateBefore15h(): void
    {
        // 14h30 le 2026-07-06 -> doit rester le 2026-07-06 14:30:00
        $date = Accounting::getAccountingDateTime('2026-07-06 14:30:00');
        $this->assertEquals('2026-07-06 14:30:00', $date);
    }

    public function testAccountingDateAfter15h(): void
    {
        // 15h01 le 2026-07-06 -> doit être reporté au 2026-07-07 08:00:00
        $date = Accounting::getAccountingDateTime('2026-07-06 15:01:00');
        $this->assertEquals('2026-07-07 08:00:00', $date);
    }

    public function testAccountingDateExact15h(): void
    {
        // 15h00 le 2026-07-06 -> doit être reporté au 2026-07-07 08:00:00
        $date = Accounting::getAccountingDateTime('2026-07-06 15:00:00');
        $this->assertEquals('2026-07-07 08:00:00', $date);
    }
}
