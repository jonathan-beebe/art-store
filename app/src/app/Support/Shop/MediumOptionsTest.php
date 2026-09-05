<?php

declare(strict_types=1);

use App\Domain\Listings\ListingStatus;
use App\Support\Shop\MediumOptions;

it('offers no options before any Medium property exists', function (): void {
    expect(MediumOptions::forStorefront())->toBe([]);
});

it('offers each attributed for-sale medium once, ordered by label, valued lowercase', function (): void {
    $seller = $this->seller();
    $oil = $this->listing($seller);
    $secondOil = $this->listing($seller);
    $ceramic = $this->listing($seller);
    $draft = $this->listing($seller, ['status' => ListingStatus::Draft]);

    $this->mediumAttribute($oil, 'Oil');
    $this->mediumAttribute($secondOil, 'Oil');
    $this->mediumAttribute($ceramic, 'Ceramic');
    $this->mediumAttribute($draft, 'Linocut');

    expect(MediumOptions::forStorefront())->toBe([
        ['value' => 'ceramic', 'label' => 'Ceramic'],
        ['value' => 'oil', 'label' => 'Oil'],
    ]);
});
