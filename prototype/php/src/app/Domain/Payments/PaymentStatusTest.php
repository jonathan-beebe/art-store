<?php

namespace App\Domain\Payments;

use PHPUnit\Framework\TestCase;

final class PaymentStatusTest extends TestCase
{
    public function test_an_approved_card_records_an_approved_payment(): void
    {
        $this->assertSame(
            PaymentStatus::Approved,
            PaymentStatus::fromCardDecision(CardDecision::approved('4242')),
        );
    }

    public function test_a_declined_card_records_a_declined_payment(): void
    {
        $this->assertSame(
            PaymentStatus::Declined,
            PaymentStatus::fromCardDecision(CardDecision::declined('0002', DeclineReason::GenericDecline)),
        );
    }
}
