<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\QueryException;

it('rejects a second favorite for the same customer and listing', function (): void {
    $customer = $this->anonymousCustomer();
    $listing = $this->listing($this->seller());
    Favorite::create(['customer_id' => $customer->id, 'listing_id' => $listing->id]);

    expect(fn () => Favorite::create(['customer_id' => $customer->id, 'listing_id' => $listing->id]))
        ->toThrow(QueryException::class);
});
