<?php

declare(strict_types=1);

namespace App\Domain\Shop;

use PHPUnit\Framework\TestCase;

final class StatusLabelTest extends TestCase
{
    public function test_it_reads_a_stored_status_as_a_sentence(): void
    {
        $this->assertSame('Pending verification', StatusLabel::humanize('pending_verification'));
        $this->assertSame('Paid', StatusLabel::humanize('paid'));
    }
}
