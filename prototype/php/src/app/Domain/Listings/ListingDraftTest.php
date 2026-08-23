<?php

declare(strict_types=1);

namespace App\Domain\Listings;

use App\Domain\Money\Money;

it('carries the typed fields a seller submitted into an attribute array', function (): void {
    $draft = ListingDraft::of('Harbour at Dusk', 'Oil on linen.', 'oil', '12 x 16 in', Money::fromCents(24900), 1);

    expect($draft->attributes())->toBe([
        'title' => 'Harbour at Dusk',
        'description' => 'Oil on linen.',
        'medium' => 'oil',
        'dimensions' => '12 x 16 in',
        'price_cents' => 24900,
        'quantity' => 1,
    ]);
});
