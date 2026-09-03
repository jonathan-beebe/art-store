<?php

declare(strict_types=1);

namespace App\Domain\Seeding;

it('carries at least one template', function (): void {
    expect(ListingTemplates::all())->not->toBe([]);
});

it('never repeats a title across two templates', function (): void {
    $titles = array_column(ListingTemplates::all(), 'title');

    expect(array_unique($titles))->toHaveCount(count($titles));
});

it('gives every template a positive price and quantity', function (): void {
    foreach (ListingTemplates::all() as $template) {
        expect($template['price_cents'])->toBeGreaterThan(0)
            ->and($template['quantity'])->toBeGreaterThan(0);
    }
});

it('returns the same order on every call, so an index addresses the same template', function (): void {
    expect(ListingTemplates::all())->toBe(ListingTemplates::all());
});
