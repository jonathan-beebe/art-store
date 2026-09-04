<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

it('reads an increase as up, signed and rounded to one decimal', function (): void {
    $change = RangeChange::between(112, 100);

    expect($change->text)->toBe('+12.0%')
        ->and($change->direction)->toBe(ChangeDirection::Up);
});

it('reads a decrease as down, with the minus-sign glyph', function (): void {
    $change = RangeChange::between(97, 100);

    expect($change->text)->toBe('−3.0%')
        ->and($change->direction)->toBe(ChangeDirection::Down);
});

it('reads a move under half a percent as flat, regardless of its sign', function (): void {
    $up = RangeChange::between(1002, 1000);
    $down = RangeChange::between(998, 1000);

    expect($up->text)->toBe('0.0%')->and($up->direction)->toBe(ChangeDirection::Flat)
        ->and($down->text)->toBe('0.0%')->and($down->direction)->toBe(ChangeDirection::Flat);
});

it('reads no previous count as new rather than dividing by zero', function (): void {
    $change = RangeChange::between(40, 0);

    expect($change->text)->toBe('new')
        ->and($change->direction)->toBe(ChangeDirection::Flat);
});

it('reads two zero counts as flat', function (): void {
    $change = RangeChange::between(0, 0);

    expect($change->text)->toBe('new')
        ->and($change->direction)->toBe(ChangeDirection::Flat);
});

it('IMPRV-030 renders empty for a caller that already knows there is nothing to compare', function (): void {
    $change = RangeChange::empty();

    expect($change->text)->toBe('')
        ->and($change->direction)->toBe(ChangeDirection::Flat);
});
