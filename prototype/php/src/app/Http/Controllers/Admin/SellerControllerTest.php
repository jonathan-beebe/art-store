<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Listings\ListingStatus;

it('lists every seller', function (): void {
    $first = $this->seller('Blue Kiln Studio');
    $second = $this->seller('Rye Press');

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/sellers');

    $response->assertOk();
    $response->assertSee('Blue Kiln Studio');
    $response->assertSee('Rye Press');
});

it('shows one seller with listing and fulfillment counts', function (): void {
    $seller = $this->seller('Blue Kiln Studio');
    $this->listing($seller, ['status' => ListingStatus::ForSale]);
    $this->listing($seller, ['status' => ListingStatus::Draft]);
    $this->orderFor($this->verifiedCustomer(), $this->listing($seller));

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/sellers/{$seller->id}");

    $response->assertOk();
    $response->assertSee('Blue Kiln Studio');
});

it('offers a form to message the seller', function (): void {
    $seller = $this->seller('Blue Kiln Studio');

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/sellers/{$seller->id}");

    $response->assertSee('Message seller');
    $response->assertSee('action="'.route('admin.sellers.messages', $seller).'"', escape: false);
});

it('sends a guest to the admin login page', function (): void {
    $response = $this->get('/admin/sellers');

    $response->assertRedirect(route('auth.admin.login'));
});
