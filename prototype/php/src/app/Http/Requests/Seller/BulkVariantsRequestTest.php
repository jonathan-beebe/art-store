<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

it('refuses a missing option value or enabled flag', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants/bulk", []);

    $response->assertSessionHasErrors(['option_value_id', 'enabled']);
});
