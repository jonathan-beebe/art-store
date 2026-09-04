<?php

declare(strict_types=1);

namespace App\Models;

it('is active while it carries no lifted_at', function (): void {
    $block = CustomerBlock::factory()->make();

    expect($block->isActive())->toBeTrue();
});

it('is not active once lifted', function (): void {
    $block = CustomerBlock::factory()->lifted()->make();

    expect($block->isActive())->toBeFalse();
});

it('records when it was lifted', function (): void {
    $block = CustomerBlock::factory()->create();

    $block->lift($this->moment('2026-08-23 10:00:00'));

    expect($block->isActive())->toBeFalse()
        ->and($block->fresh()?->lifted_at?->format('Y-m-d H:i:s'))->toBe('2026-08-23 10:00:00');
});
