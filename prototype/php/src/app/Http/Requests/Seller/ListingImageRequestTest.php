<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use Illuminate\Http\UploadedFile;

it('refuses a file type other than jpeg, png, webp, or gif', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/images", [
        'image' => UploadedFile::fake()->create('notes.txt', 10),
    ]);

    $response->assertSessionHasErrors('image');
});

it('requires an image to upload', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/images", []);

    $response->assertSessionHasErrors('image');
});
