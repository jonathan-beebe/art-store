<?php

declare(strict_types=1);

namespace App\Domain\Money;

use InvalidArgumentException;
use Stringable;

final readonly class Money implements Stringable
{
    private function __construct(public int $cents) {}

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Reads the dollars-and-cents string a price field submits. String parsing
     * rather than a float multiply so a large price keeps every cent.
     */
    public static function fromDollars(string $dollars): self
    {
        $amount = trim($dollars);

        if (preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $amount, $parts) !== 1) {
            throw new InvalidArgumentException("Dollars must be a number with at most two decimal places, got \"{$dollars}\".");
        }

        [, $sign, $whole, $fraction] = $parts + [3 => '0'];
        $cents = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');

        return new self($sign === '-' ? -$cents : $cents);
    }

    public function add(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function subtract(self $other): self
    {
        return new self($this->cents - $other->cents);
    }

    public function multiply(int $quantity): self
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException("Quantity must not be negative, got {$quantity}.");
        }

        return new self($this->cents * $quantity);
    }

    public function percent(int $percent): self
    {
        if ($percent < 0 || $percent > 100) {
            throw new InvalidArgumentException("Percent must be between 0 and 100, got {$percent}.");
        }

        $scaled = $this->cents * $percent;
        $sign = $scaled < 0 ? -1 : 1;

        // Half a cent rounds away from zero, so a platform fee never under-collects.
        return new self($sign * intdiv(abs($scaled) + 50, 100));
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents;
    }

    public function isPositive(): bool
    {
        return $this->cents > 0;
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function format(): string
    {
        $sign = $this->cents < 0 ? '-' : '';

        return $sign.'$'.number_format(abs($this->cents) / 100, 2, '.', ',');
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
