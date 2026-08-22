<?php

namespace App\Domain\Escrow;

use PHPUnit\Framework\TestCase;

final class LedgerEntryTypeTest extends TestCase
{
    public function test_it_names_the_three_stages_money_passes_through(): void
    {
        $this->assertSame(
            ['held', 'released', 'paid_out'],
            array_column(LedgerEntryType::cases(), 'value'),
        );
    }
}
