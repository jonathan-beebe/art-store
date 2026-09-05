<?php

declare(strict_types=1);

namespace App\Seller\Store;

use DateTimeImmutable;

it('says one piece for sale in the singular', function (): void {
    expect((new StoreFacts(1, null))->sentence())->toBe('1 piece for sale');
});

it('says N pieces for sale in the plural', function (): void {
    expect((new StoreFacts(2, null))->sentence())->toBe('2 pieces for sale');
});

it('joins the piece count and the selling-since line with a middle dot', function (): void {
    $facts = new StoreFacts(2, new DateTimeImmutable('2026-03-14'));

    expect($facts->sentence())->toBe('2 pieces for sale · Selling since March 2026');
});

it('drops the selling-since half when there is nothing to say', function (): void {
    expect((new StoreFacts(1, null))->sentence())->not->toContain('Selling since');
});
