<?php

declare(strict_types=1);

namespace App\Domain\Seeding;

it('draws the same sequence from the same seed', function (): void {
    $a = Lcg::seeded(2026);
    $b = Lcg::seeded(2026);

    $drawnByA = array_map(fn (): int => $a->nextInt(1000), range(1, 20));
    $drawnByB = array_map(fn (): int => $b->nextInt(1000), range(1, 20));

    expect($drawnByA)->toBe($drawnByB);
});

it('draws a different sequence from a different seed', function (): void {
    $a = Lcg::seeded(2026);
    $b = Lcg::seeded(2027);

    $drawnByA = array_map(fn (): int => $a->nextInt(1000), range(1, 20));
    $drawnByB = array_map(fn (): int => $b->nextInt(1000), range(1, 20));

    expect($drawnByA)->not->toBe($drawnByB);
});

it('never draws outside the requested upper bound', function (): void {
    $lcg = Lcg::seeded(7);

    foreach (range(1, 500) as $ignored) {
        expect($lcg->nextInt(5))->toBeGreaterThanOrEqual(0)->toBeLessThan(5);
    }
});

it('always draws zero from an empty or negative pool', function (): void {
    $lcg = Lcg::seeded(7);

    expect($lcg->nextInt(0))->toBe(0)
        ->and($lcg->nextInt(-3))->toBe(0);
});

it('draws a float within [0.0, 1.0)', function (): void {
    $lcg = Lcg::seeded(11);

    foreach (range(1, 100) as $ignored) {
        $value = $lcg->nextFloat();

        expect($value)->toBeGreaterThanOrEqual(0.0)->toBeLessThan(1.0);
    }
});

it('folds a seed outside the modulus into range without throwing', function (): void {
    $lcg = Lcg::seeded(-5);

    expect($lcg->nextInt(1000))->toBeGreaterThanOrEqual(0);
});

it('picks the weighted index a draw falls into', function (): void {
    $lcg = Lcg::seeded(2026);

    $picks = array_map(fn (): int => $lcg->weightedIndex([90, 5, 5]), range(1, 200));

    // Overwhelmingly weighted toward index 0, but every option is reachable.
    expect(array_unique($picks))->toContain(0)
        ->and(array_sum($picks))->toBeGreaterThan(0);
});

it('always picks the only index in a one-option list', function (): void {
    $lcg = Lcg::seeded(2026);

    expect($lcg->weightedIndex([1]))->toBe(0);
});
