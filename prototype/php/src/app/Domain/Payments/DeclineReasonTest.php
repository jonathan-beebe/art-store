<?php

declare(strict_types=1);

namespace App\Domain\Payments;

use PHPUnit\Framework\TestCase;

final class DeclineReasonTest extends TestCase
{
    public function test_it_reads_back_as_a_sentence_for_the_checkout_page(): void
    {
        $this->assertSame('Your card was declined.', DeclineReason::GenericDecline->message());
        $this->assertSame('Your card has insufficient funds.', DeclineReason::InsufficientFunds->message());
        $this->assertSame('That card number is not valid.', DeclineReason::InvalidCardNumber->message());
    }
}
