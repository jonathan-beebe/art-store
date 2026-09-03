<?php

declare(strict_types=1);

namespace App\Domain\Seeding;

/**
 * A linear congruential generator: the domain core's own source of
 * repeatable randomness, so `App\Domain\Seeding\ActivityPlan` never reaches
 * for `random_int()` or `mt_rand()`, both of which
 * {@see \Tests\Arch::domainStaysPure()} refuses. The same seed advanced
 * through the same sequence of calls always draws the same values — the
 * property the whole plan's determinism rests on. The constants are the
 * "Numerical Recipes" parameters: a 32-bit modulus wide enough that a plan
 * spanning hundreds of draws shows no visible repetition.
 */
final class Lcg
{
    private const int MULTIPLIER = 1664525;

    private const int INCREMENT = 1013904223;

    private const int MODULUS = 4294967296;

    private function __construct(private int $state) {}

    /**
     * `$seed` is folded into range before it seeds the first state, so a
     * negative or oversized seed still starts a valid generator.
     */
    public static function seeded(int $seed): self
    {
        $folded = $seed % self::MODULUS;

        return new self($folded < 0 ? $folded + self::MODULUS : $folded);
    }

    /**
     * The next value in `[0, $upperExclusive)`, advancing this generator's
     * state. `$upperExclusive` of 0 or less always draws 0, so a caller
     * naming an empty pool never divides by zero.
     *
     * @phpstan-impure two calls with the same `$upperExclusive` draw
     * different values, because this generator's state advances between
     * them.
     */
    public function nextInt(int $upperExclusive): int
    {
        $this->state = (self::MULTIPLIER * $this->state + self::INCREMENT) % self::MODULUS;

        return $upperExclusive > 0 ? $this->state % $upperExclusive : 0;
    }

    /**
     * A value in `[0.0, 1.0)`, the shape a probability check reads most
     * naturally — `nextInt()`'s draw divided by the modulus it is drawn
     * from.
     *
     * @phpstan-impure see {@see nextInt()}.
     */
    public function nextFloat(): float
    {
        return $this->nextInt(PHP_INT_MAX) / PHP_INT_MAX;
    }

    /**
     * Picks the index of the first cumulative weight in `$weights` that
     * clears a draw against their sum — the standard weighted-choice
     * construction, so a caller lists options once as `[label => weight]`
     * pairs instead of writing its own cumulative loop. Every weight is
     * assumed positive; the last index is returned if rounding leaves the
     * draw past every cumulative weight.
     *
     * @param  list<int>  $weights
     *
     * @phpstan-impure see {@see nextInt()}.
     */
    public function weightedIndex(array $weights): int
    {
        $total = array_sum($weights);
        $draw = $this->nextInt(max(1, $total));
        $cumulative = 0;

        foreach ($weights as $index => $weight) {
            $cumulative += $weight;

            if ($draw < $cumulative) {
                return $index;
            }
        }

        return count($weights) - 1;
    }
}
