<?php

declare(strict_types=1);

namespace App\Domain\Payments;

use PHPUnit\Framework\TestCase;

final class CardDecisionTest extends TestCase
{
    public function test_an_approval_carries_no_decline_reason(): void
    {
        $decision = CardDecision::approved('4242');

        $this->assertTrue($decision->isApproved);
        $this->assertSame('4242', $decision->lastFour);
        $this->assertNull($decision->declineReason);
    }

    public function test_a_decline_carries_its_reason(): void
    {
        $decision = CardDecision::declined('9995', DeclineReason::InsufficientFunds);

        $this->assertFalse($decision->isApproved);
        $this->assertSame('9995', $decision->lastFour);
        $this->assertSame(DeclineReason::InsufficientFunds, $decision->declineReason);
    }
}
