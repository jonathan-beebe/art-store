<?php

namespace App\Domain\Payments;

use PHPUnit\Framework\TestCase;

final class FakeCardTest extends TestCase
{
    public function test_the_test_card_is_approved(): void
    {
        $decision = FakeCard::decide('4242 4242 4242 4242');

        $this->assertTrue($decision->isApproved);
        $this->assertNull($decision->declineReason);
    }

    public function test_the_generic_decline_card_is_declined(): void
    {
        $decision = FakeCard::decide('4000 0000 0000 0002');

        $this->assertFalse($decision->isApproved);
        $this->assertSame(DeclineReason::GenericDecline, $decision->declineReason);
    }

    public function test_the_insufficient_funds_card_is_declined(): void
    {
        $decision = FakeCard::decide('4000 0000 0000 9995');

        $this->assertFalse($decision->isApproved);
        $this->assertSame(DeclineReason::InsufficientFunds, $decision->declineReason);
    }

    public function test_an_unknown_number_is_declined_as_invalid(): void
    {
        $decision = FakeCard::decide('1234 5678 9012 3456');

        $this->assertFalse($decision->isApproved);
        $this->assertSame(DeclineReason::InvalidCardNumber, $decision->declineReason);
    }

    public function test_it_ignores_spaces_and_dashes(): void
    {
        $this->assertTrue(FakeCard::decide('4242-4242-4242-4242')->isApproved);
        $this->assertTrue(FakeCard::decide('4242424242424242')->isApproved);
        $this->assertTrue(FakeCard::decide('  4242 4242-4242 4242  ')->isApproved);
    }

    public function test_it_exposes_the_last_four_digits(): void
    {
        $this->assertSame('9995', FakeCard::decide('4000-0000-0000-9995')->lastFour);
    }

    public function test_it_exposes_every_digit_of_a_number_shorter_than_four(): void
    {
        $this->assertSame('42', FakeCard::decide('42')->lastFour);
    }

    public function test_an_empty_number_is_declined_as_invalid(): void
    {
        $decision = FakeCard::decide('   ');

        $this->assertFalse($decision->isApproved);
        $this->assertSame('', $decision->lastFour);
        $this->assertSame(DeclineReason::InvalidCardNumber, $decision->declineReason);
    }
}
