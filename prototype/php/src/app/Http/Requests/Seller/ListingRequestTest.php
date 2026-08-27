<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\Category;
use App\Models\Listing;
use App\Models\OptionAxis;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
$form = function (array $overrides = []): array {
    return $overrides + [
        'title' => 'Harbour at Dusk',
        'description' => 'Oil on linen.',
        'dimensions' => '12 x 16 in',
        'price' => '249.00',
        'quantity' => 1,
    ];
};

it('rejects invalid listing input', function (array $overrides, string $field) use ($form): void {
    $response = $this->actingAs($this->seller(), 'seller')
        ->post('/seller/listings', $form($overrides));

    $response->assertSessionHasErrors($field);
    expect(Listing::count())->toBe(0);
})->with([
    'a listing without a title' => [['title' => ''], 'title'],
    'a title longer than the column' => [['title' => str_repeat('a', 256)], 'title'],
    'a price that is not an amount in dollars' => [['price' => 'a lot'], 'price'],
    'a price carrying fractions of a cent' => [['price' => '249.999'], 'price'],
    'no quantity' => [['quantity' => ''], 'quantity'],
    'a negative quantity' => [['quantity' => -1], 'quantity'],
    'more pieces than a studio makes' => [['quantity' => 1000], 'quantity'],
]);

it('rejects a category id that does not exist', function () use ($form): void {
    $response = $this->actingAs($this->seller(), 'seller')
        ->post('/seller/listings', $form(['category_id' => 'cat_does_not_exist']));

    $response->assertSessionHasErrors('category_id');
    expect(Listing::count())->toBe(0);
});

it('accepts a listing with no category', function () use ($form): void {
    $response = $this->actingAs($this->seller(), 'seller')
        ->post('/seller/listings', $form());

    $response->assertSessionDoesntHaveErrors('category_id');
    expect(Listing::sole()->category_id)->toBeNull();
});

it('saves the category a seller picked', function () use ($form): void {
    $category = Category::factory()->create();

    $this->actingAs($this->seller(), 'seller')
        ->post('/seller/listings', $form(['category_id' => $category->id]));

    expect(Listing::sole()->category_id)->toBe($category->id);
});

it('rejects an invalid image upload', function (string $filename, int $kilobytes, string $mimeType) use ($form): void {
    Storage::fake('public');

    $response = $this->actingAs($this->seller(), 'seller')->post('/seller/listings', $form([
        'image' => UploadedFile::fake()->create($filename, $kilobytes, $mimeType),
    ]));

    $response->assertSessionHasErrors('image');
    expect(Listing::count())->toBe(0);
})->with([
    'a file that is not an image at all' => ['notes.txt', 4, 'text/plain'],
    'a file that only claims to be an image' => ['harbour.jpg', 12, 'image/jpeg'],
]);

it('rejects an image over the upload limit', function () use ($form): void {
    Storage::fake('public');

    $response = $this->actingAs($this->seller(), 'seller')->post('/seller/listings', $form([
        'image' => UploadedFile::fake()->image('harbour.jpg')->size(5121),
    ]));

    $response->assertSessionHasErrors('image');
    expect(Listing::count())->toBe(0);
});

it('accepts a listing with zero quantity', function () use ($form): void {
    $response = $this->actingAs($this->seller(), 'seller')
        ->post('/seller/listings', $form(['quantity' => 0]));

    $response->assertSessionDoesntHaveErrors('quantity');
    expect(Listing::sole()->quantity)->toBe(0);
});

it('says a price is an amount in dollars', function () use ($form): void {
    $response = $this->actingAs($this->seller(), 'seller')
        ->post('/seller/listings', $form(['price' => 'a lot']));

    $response->assertSessionHasErrors(['price' => 'The price is an amount in dollars, like 249.00.']);
});

it('answers another sellers listing before it validates the form', function () use ($form): void {
    $listing = $this->listing($this->seller('Other Studio'), ['title' => 'Not Mine']);

    $response = $this->actingAs($this->seller(), 'seller')
        ->put("/seller/listings/{$listing->id}", $form(['title' => '']));

    $response->assertNotFound();
    $response->assertSessionHasNoErrors();
    expect($listing->refresh()->title)->toBe('Not Mine');
});

it('reads the typed fields into a draft', function () use ($form): void {
    $draft = ListingRequest::create('/seller/listings', 'POST', $form())->toDraft();

    expect($draft->title)->toBe('Harbour at Dusk')
        ->and($draft->description)->toBe('Oil on linen.')
        ->and($draft->dimensions)->toBe('12 x 16 in')
        ->and($draft->price)->toBeMoney(24900)
        ->and($draft->quantity)->toBe(1)
        ->and($draft->categoryId)->toBeNull();
});

it('leaves an optional field the seller skipped null', function (string $field) use ($form): void {
    $draft = ListingRequest::create('/seller/listings', 'POST', $form([$field => '']))->toDraft();

    expect($draft->{$field})->toBeNull();
})->with(['description', 'dimensions']);

it('does not require price or quantity to update a listing that already offers a choice', function () use ($form): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $payload = $form();
    unset($payload['price'], $payload['quantity']);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}", $payload);

    $response->assertSessionDoesntHaveErrors(['price', 'quantity']);
});

it('still requires price and quantity to update a listing with no choices and no serialized pieces', function () use ($form): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $payload = $form();
    unset($payload['price'], $payload['quantity']);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}", $payload);

    $response->assertSessionHasErrors(['price', 'quantity']);
});
