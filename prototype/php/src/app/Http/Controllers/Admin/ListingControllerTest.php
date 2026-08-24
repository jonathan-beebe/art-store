<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Cart\AddToCart;
use App\Domain\Listings\ListingStatus;
use App\Models\Favorite;

it('lists listings across every seller', function (): void {
    $this->listing($this->seller('Blue Kiln Studio'), ['title' => 'Nine Herons']);
    $this->listing($this->seller('Rye Press'), ['title' => 'Rye Harvest']);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/listings');

    $response->assertOk();
    $response->assertSee('Nine Herons');
    $response->assertSee('Rye Harvest');
    $response->assertSee('Blue Kiln Studio');
    $response->assertSee('Rye Press');
});

it('narrows the list to one status', function (): void {
    $seller = $this->seller();
    $this->listing($seller, ['title' => 'Nine Herons', 'status' => ListingStatus::ForSale]);
    $this->listing($seller, ['title' => 'Rye Harvest', 'status' => ListingStatus::Draft]);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/listings?status=draft');

    $response->assertOk();
    $response->assertSee('Rye Harvest');
    $response->assertDontSee('Nine Herons');
});

it('narrows the list to one seller', function (): void {
    $kiln = $this->seller('Blue Kiln Studio');
    $this->listing($kiln, ['title' => 'Nine Herons']);
    $this->listing($this->seller('Rye Press'), ['title' => 'Rye Harvest']);

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/listings?seller={$kiln->id}");

    $response->assertOk();
    $response->assertSee('Nine Herons');
    $response->assertDontSee('Rye Harvest');
});

it('reads an empty filter as every listing, the way the console submits it', function (string $query): void {
    $this->listing($this->seller('Blue Kiln Studio'), ['title' => 'Nine Herons', 'status' => ListingStatus::ForSale]);
    $this->listing($this->seller('Rye Press'), ['title' => 'Rye Harvest', 'status' => ListingStatus::Draft]);

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/listings?{$query}");

    $response->assertOk();
    $response->assertSee('Nine Herons');
    $response->assertSee('Rye Harvest');
})->with([
    'no filters at all' => '',
    'both filters empty' => 'status=&seller=',
    'a status that names nothing' => 'status=nonsense',
]);

it('says so when no listing matches the filters', function (): void {
    $this->listing($this->seller(), ['status' => ListingStatus::ForSale]);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/listings?status=archived');

    $response->assertOk();
    $response->assertSee('No listings.');
});

it('shows one listing with its activity and sales', function (): void {
    $seller = $this->seller('Blue Kiln Studio');
    $listing = $this->listing($seller, ['title' => 'Nine Herons', 'medium' => 'Oil on linen']);
    $customer = $this->verifiedCustomer();
    Favorite::factory()->create(['customer_id' => $customer->id, 'listing_id' => $listing->id]);
    app(AddToCart::class)($this->cartFor($customer), $listing, 1, $this->moment('2026-08-20 08:00:00'));
    $order = $this->orderFor($this->verifiedCustomer(), $listing);

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/listings/{$listing->id}");

    $response->assertOk();
    $response->assertSee('Nine Herons');
    $response->assertSee('Oil on linen');
    $response->assertSee('Blue Kiln Studio');
    $response->assertSee($order->id);
});

it('says so on a listing nobody has bought', function (): void {
    $listing = $this->listing($this->seller());

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/listings/{$listing->id}");

    $response->assertOk();
    $response->assertSee('No sales yet.');
});

it('sends a guest to the admin login page', function (): void {
    $this->get('/admin/listings')->assertRedirect(route('auth.admin.login'));
});

it('answers not found for a value that is not a listing id, the same as an unknown one', function (string $id): void {
    $this->actingAs($this->admin(), 'admin')->get("/admin/listings/{$id}")->assertNotFound();
})->with([
    'another table prefix' => 'sel_01J5X3M9A2K8YB7Q4R6T1V0WZE',
    'a bare ULID' => '01J5X3M9A2K8YB7Q4R6T1V0WZE',
    'a value of no shape at all' => 'nonsense',
    'a listing that does not exist' => 'lst_01J5X3M9A2K8YB7Q4R6T1V0WZE',
]);
