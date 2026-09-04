<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\FulfillmentFlow;
use App\Models\Listing;
use App\Models\OptionAxis;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
$form = function (array $overrides = []): array {
    return $overrides + [
        'shape' => 'one',
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

it('rejects a create with no shape at all', function () use ($form): void {
    $payload = $form();
    unset($payload['shape']);

    $response = $this->actingAs($this->seller(), 'seller')->post('/seller/listings', $payload);

    $response->assertSessionHasErrors('shape');
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

it('does not require a quantity for a made-to-order create', function () use ($form): void {
    $response = $this->actingAs($this->seller(), 'seller')
        ->post('/seller/listings', $form(['quantity' => '', 'made_to_order' => '1']));

    $response->assertSessionDoesntHaveErrors('quantity');
    expect(Listing::sole()->quantity)->toBeNull();
});

it('answers another sellers listing before it validates the form', function () use ($form): void {
    $listing = $this->listing($this->seller('Other Studio'), ['title' => 'Not Mine']);

    $response = $this->actingAs($this->seller(), 'seller')
        ->put("/seller/listings/{$listing->id}", $form(['title' => '']));

    $response->assertNotFound();
    $response->assertSessionHasNoErrors();
    expect($listing->refresh()->title)->toBe('Not Mine');
});

it('reads the one-thing shape into a draft with no description, dimensions, or category', function () use ($form): void {
    $draft = ListingRequest::create('/seller/listings', 'POST', $form())->toDraft();

    expect($draft->title)->toBe('Harbour at Dusk')
        ->and($draft->description)->toBeNull()
        ->and($draft->dimensions)->toBeNull()
        ->and($draft->price)->toBeMoney(24900)
        ->and($draft->quantity)->toBe(1)
        ->and($draft->categoryId)->toBeNull();
});

it('reads a made-to-order one-thing submission into a null quantity', function () use ($form): void {
    $draft = ListingRequest::create('/seller/listings', 'POST', $form(['made_to_order' => '1']))->toDraft();

    expect($draft->quantity)->toBeNull();
});

it('reads the versions shape into a zero-priced, quantity-less draft', function (): void {
    $draft = ListingRequest::create('/seller/listings', 'POST', [
        'shape' => 'versions',
        'title' => 'Sunset Ridge',
        'choice_name' => 'Size',
        'versions' => [['label' => '8x10', 'price' => '18.00']],
    ])->toDraft();

    expect($draft->title)->toBe('Sunset Ridge')
        ->and($draft->price)->toBeMoney(0)
        ->and($draft->quantity)->toBeNull();
});

it('reads the extras shape into a draft carrying the items own price and quantity', function (): void {
    $draft = ListingRequest::create('/seller/listings', 'POST', [
        'shape' => 'extras',
        'title' => 'Maple Serving Board',
        'price' => '46.00',
        'quantity' => 12,
    ])->toDraft();

    expect($draft->price)->toBeMoney(4600)
        ->and($draft->quantity)->toBe(12);
});

it('rejects a versions submission with no complete version row', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->post('/seller/listings', [
        'shape' => 'versions',
        'title' => 'Sunset Ridge',
        'choice_name' => 'Size',
        'versions' => [['label' => '', 'price' => '']],
    ]);

    $response->assertSessionHasErrors('versions');
});

it('flags a version row missing only its label', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->post('/seller/listings', [
        'shape' => 'versions',
        'title' => 'Sunset Ridge',
        'choice_name' => 'Size',
        'versions' => [['label' => '', 'price' => '18.00']],
    ]);

    $response->assertSessionHasErrors('versions.0.label');
});

it('flags a version row with a price that does not parse', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->post('/seller/listings', [
        'shape' => 'versions',
        'title' => 'Sunset Ridge',
        'choice_name' => 'Size',
        'versions' => [['label' => '8x10', 'price' => 'not a price']],
    ]);

    $response->assertSessionHasErrors('versions.0.price');
});

it('rejects an extras choice name given with no complete option row', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->post('/seller/listings', [
        'shape' => 'extras',
        'title' => 'Maple Serving Board',
        'price' => '46.00',
        'quantity' => 12,
        'extra_choice_name' => 'Finish',
    ]);

    $response->assertSessionHasErrors('extra_options');
});

it('requires a choice name on the versions shape', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->post('/seller/listings', [
        'shape' => 'versions',
        'title' => 'Sunset Ridge',
        'choice_name' => '',
        'versions' => [['label' => '8x10', 'price' => '18.00']],
    ]);

    $response->assertSessionHasErrors('choice_name');
});

it('lets the extras shape through with no extra entered at all', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->post('/seller/listings', [
        'shape' => 'extras',
        'title' => 'Maple Serving Board',
        'price' => '46.00',
        'quantity' => 12,
    ]);

    $response->assertSessionDoesntHaveErrors();
});

it('requires an extra choice name once an option row is filled in', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->post('/seller/listings', [
        'shape' => 'extras',
        'title' => 'Maple Serving Board',
        'price' => '46.00',
        'quantity' => 12,
        'extra_options' => [['label' => 'Oil finish', 'price' => '+0.00']],
    ]);

    $response->assertSessionHasErrors('extra_choice_name');
});

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

it('does not require a quantity to update a made-to-order listing', function () use ($form): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $payload = $form();
    unset($payload['quantity']);
    $payload['made_to_order'] = '1';

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}", $payload);

    $response->assertSessionDoesntHaveErrors('quantity');
    expect($listing->refresh()->quantity)->toBeNull();
});

it('sets the listings workflow to one the seller owns', function () use ($form): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $flow = FulfillmentFlow::factory()->create(['seller_id' => $seller->id]);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}", $form(['fulfillment_flow_id' => $flow->id]));

    $response->assertSessionDoesntHaveErrors('fulfillment_flow_id');
    expect($listing->refresh()->fulfillment_flow_id)->toBe($flow->id);
});

it('refuses a workflow that belongs to another seller', function () use ($form): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $other = FulfillmentFlow::factory()->create(['seller_id' => $this->seller('Other Studio')->id]);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}", $form(['fulfillment_flow_id' => $other->id]));

    $response->assertSessionHasErrors('fulfillment_flow_id');
    expect($listing->refresh()->fulfillment_flow_id)->toBeNull();
});

it('reads the blank picker option as the sellers default, clearing a listings own workflow', function () use ($form): void {
    $seller = $this->seller();
    $flow = FulfillmentFlow::factory()->create(['seller_id' => $seller->id]);
    $listing = $this->listing($seller, ['fulfillment_flow_id' => $flow->id]);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}", $form(['fulfillment_flow_id' => '']));

    $response->assertSessionDoesntHaveErrors('fulfillment_flow_id');
    expect($listing->refresh()->fulfillment_flow_id)->toBeNull();
});

it('leaves a listings workflow untouched when the field is absent, the way one flow leaves the picker unrendered', function () use ($form): void {
    $seller = $this->seller();
    $flow = FulfillmentFlow::factory()->create(['seller_id' => $seller->id]);
    $listing = $this->listing($seller, ['fulfillment_flow_id' => $flow->id]);
    $payload = $form();
    unset($payload['fulfillment_flow_id']);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}", $payload);

    $response->assertSessionDoesntHaveErrors('fulfillment_flow_id');
    expect($listing->refresh()->fulfillment_flow_id)->toBe($flow->id);
});
