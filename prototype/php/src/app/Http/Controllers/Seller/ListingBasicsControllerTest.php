<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

it('refuses another sellers basics screen', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));

    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/listings/{$listing->id}/basics");

    $response->assertNotFound();
});
