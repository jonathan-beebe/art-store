<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use InvalidArgumentException;

it('adds a quantity break tier to a listing', function (): void {
    $listing = $this->listing($this->seller());

    $break = app(AddQuantityBreak::class)($listing, 100, 1000);

    expect($break->listing_id)->toBe($listing->id)
        ->and($break->min_qty)->toBe(100)
        ->and($break->discount_bps)->toBe(1000);
});

it('refuses a tier the domain would refuse', function (): void {
    app(AddQuantityBreak::class)($this->listing($this->seller()), 1, 1000);
})->throws(InvalidArgumentException::class);
