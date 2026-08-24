<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Listings\RemoveListing;
use App\Domain\Listings\ListingRemovalKind;
use App\Models\ListingRemoval;

it('removes a listing with the submitted kind and reason', function (): void {
    $listing = $this->listing($this->seller());

    $response = $this->actingAs($this->admin(), 'admin')
        ->post("/admin/listings/{$listing->id}/removals", ['kind' => 'temporary', 'reason' => 'Under review.']);

    $response->assertRedirect(route('admin.listings.show', $listing));
    $removal = ListingRemoval::sole();
    expect($removal->kind)->toBe(ListingRemovalKind::Temporary)
        ->and($removal->reason)->toBe('Under review.')
        ->and($listing->isOnStorefront())->toBeFalse();
});

it('refuses to remove a listing that already has an active removal', function (): void {
    $listing = $this->listing($this->seller());
    app(RemoveListing::class)($listing, ListingRemovalKind::Temporary, 'First reason.');

    $response = $this->actingAs($this->admin(), 'admin')
        ->post("/admin/listings/{$listing->id}/removals", ['kind' => 'temporary', 'reason' => 'Second reason.']);

    $response->assertSessionHasErrors();
    expect(ListingRemoval::count())->toBe(1);
});

it('sends a guest to the admin login page', function (): void {
    $listing = $this->listing($this->seller());

    $response = $this->post("/admin/listings/{$listing->id}/removals", ['kind' => 'temporary', 'reason' => 'Under review.']);

    $response->assertRedirect(route('auth.admin.login'));
});
