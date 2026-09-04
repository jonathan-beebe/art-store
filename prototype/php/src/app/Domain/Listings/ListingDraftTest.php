<?php

declare(strict_types=1);

namespace App\Domain\Listings;

use App\Domain\Money\Money;

it('carries the typed fields a seller submitted into an attribute array', function (): void {
    $draft = ListingDraft::of('Harbour at Dusk', 'Oil on linen.', '12 x 16 in', Money::fromCents(24900), 1);

    expect($draft->attributes())->toBe([
        'title' => 'Harbour at Dusk',
        'description' => 'Oil on linen.',
        'dimensions' => '12 x 16 in',
        'price_cents' => 24900,
        'quantity' => 1,
        'category_id' => null,
        'fulfillment_flow_id' => null,
    ]);
});

it('carries the category a seller picked into the attribute array', function (): void {
    $draft = ListingDraft::of('Harbour at Dusk', 'Oil on linen.', '12 x 16 in', Money::fromCents(24900), 1, 'cat_01');

    expect($draft->attributes()['category_id'])->toBe('cat_01');
});

it('carries the workflow a seller picked into the attribute array', function (): void {
    $draft = ListingDraft::of('Harbour at Dusk', 'Oil on linen.', '12 x 16 in', Money::fromCents(24900), 1, fulfillmentFlowId: 'ffl_01');

    expect($draft->attributes()['fulfillment_flow_id'])->toBe('ffl_01');
});
