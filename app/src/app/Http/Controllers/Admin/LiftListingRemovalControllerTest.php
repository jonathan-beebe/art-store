<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Listings\RemoveListing;
use App\Domain\Listings\ListingRemovalKind;

it('lifts an active temporary removal', function (): void {
    $listing = $this->listing($this->seller());
    app(RemoveListing::class)($listing, ListingRemovalKind::Temporary, 'Under review.');

    $response = $this->actingAs($this->admin(), 'admin')
        ->post("/admin/listings/{$listing->id}/removals/lift");

    $response->assertRedirect(route('admin.listings.show', $listing));
    expect($listing->hasActiveRemoval())->toBeFalse();
});

it('refuses to lift a listing with no active removal', function (): void {
    $listing = $this->listing($this->seller());

    $response = $this->actingAs($this->admin(), 'admin')
        ->post("/admin/listings/{$listing->id}/removals/lift");

    $response->assertSessionHasErrors();
});

it('refuses to lift a permanent removal', function (): void {
    $listing = $this->listing($this->seller());
    app(RemoveListing::class)($listing, ListingRemovalKind::Permanent, 'Counterfeit.');

    $response = $this->actingAs($this->admin(), 'admin')
        ->post("/admin/listings/{$listing->id}/removals/lift");

    $response->assertSessionHasErrors();
    expect($listing->hasActiveRemoval())->toBeTrue();
});
