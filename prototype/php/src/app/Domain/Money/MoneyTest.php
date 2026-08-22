<?php

namespace App\Domain\Money;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_it_holds_the_cents_it_was_built_from(): void
    {
        $this->assertSame(1234, Money::fromCents(1234)->cents);
    }

    public function test_it_adds_another_amount(): void
    {
        $sum = Money::fromCents(1234)->add(Money::fromCents(66));

        $this->assertSame(1300, $sum->cents);
    }

    public function test_it_leaves_the_operands_untouched_when_adding(): void
    {
        $subtotal = Money::fromCents(1234);
        $subtotal->add(Money::fromCents(66));

        $this->assertSame(1234, $subtotal->cents);
    }

    public function test_it_multiplies_by_a_quantity(): void
    {
        $this->assertSame(3702, Money::fromCents(1234)->multiply(3)->cents);
    }

    public function test_it_rejects_a_negative_quantity(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromCents(1234)->multiply(-1);
    }

    public function test_it_takes_a_percentage(): void
    {
        $this->assertSame(123, Money::fromCents(1230)->percent(10)->cents);
    }

    public function test_it_rounds_a_half_cent_percentage_up(): void
    {
        // 10% of 1235 is 123.5 cents; the platform fee never under-collects.
        $this->assertSame(124, Money::fromCents(1235)->percent(10)->cents);
    }

    public function test_it_rounds_a_negative_half_cent_percentage_away_from_zero(): void
    {
        $this->assertSame(-124, Money::fromCents(-1235)->percent(10)->cents);
    }

    public function test_it_rejects_a_percentage_outside_zero_to_one_hundred(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromCents(1234)->percent(101);
    }

    public function test_it_formats_as_dollars_and_cents(): void
    {
        $this->assertSame('$12.34', Money::fromCents(1234)->format());
    }

    public function test_it_formats_thousands_with_a_separator(): void
    {
        $this->assertSame('$1,234,567.89', Money::fromCents(123456789)->format());
    }

    public function test_it_formats_a_negative_amount_with_a_leading_sign(): void
    {
        $this->assertSame('-$12.34', Money::fromCents(-1234)->format());
    }

    public function test_it_formats_zero(): void
    {
        $this->assertSame('$0.00', Money::fromCents(0)->format());
    }

    public function test_it_reads_dollars_and_cents_typed_into_a_price_field(): void
    {
        $this->assertSame(1234, Money::fromDollars('12.34')->cents);
    }

    public function test_it_reads_whole_dollars(): void
    {
        $this->assertSame(1200, Money::fromDollars('12')->cents);
    }

    public function test_it_pads_a_single_decimal_place(): void
    {
        $this->assertSame(1250, Money::fromDollars('12.5')->cents);
    }

    public function test_it_reads_a_price_with_surrounding_whitespace(): void
    {
        $this->assertSame(1234, Money::fromDollars(' 12.34 ')->cents);
    }

    public function test_it_reads_a_price_too_large_for_a_float_to_hold_exactly(): void
    {
        $this->assertSame(8070450532247928, Money::fromDollars('80704505322479.28')->cents);
    }

    public function test_it_rejects_a_price_that_is_not_a_number(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromDollars('twelve');
    }

    public function test_it_rejects_a_price_carrying_more_than_two_decimal_places(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromDollars('12.345');
    }
}
