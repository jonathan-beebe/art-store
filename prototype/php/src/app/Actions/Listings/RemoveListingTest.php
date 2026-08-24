<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Domain\DomainRuleViolation;
use App\Domain\Listings\ListingRemovalKind;
use App\Models\ListingRemoval;

it('removes a listing with a kind and a reason', function (): void {
    $listing = $this->listing($this->seller());

    $removal = app(RemoveListing::class)($listing, ListingRemovalKind::Temporary, 'Under review for a copyright claim.');

    expect($removal->listing_id)->toBe($listing->id)
        ->and($removal->kind)->toBe(ListingRemovalKind::Temporary)
        ->and($removal->reason)->toBe('Under review for a copyright claim.')
        ->and($removal->isActive())->toBeTrue()
        ->and($listing->hasActiveRemoval())->toBeTrue();
});

it('removes a listing permanently', function (): void {
    $listing = $this->listing($this->seller());

    $removal = app(RemoveListing::class)($listing, ListingRemovalKind::Permanent, 'Counterfeit.');

    expect($removal->kind)->toBe(ListingRemovalKind::Permanent);
});

it('refuses a listing that already has an active removal', function (): void {
    $listing = $this->listing($this->seller());
    ListingRemoval::factory()->create(['listing_id' => $listing->id]);

    $remove = fn () => app(RemoveListing::class)($listing, ListingRemovalKind::Temporary, 'Second reason.');

    expect($remove)->toThrow(DomainRuleViolation::class, 'This listing already has an active removal.')
        ->and(ListingRemoval::count())->toBe(1);
});

it('removes a listing again once an earlier removal was lifted', function (): void {
    $listing = $this->listing($this->seller());
    ListingRemoval::factory()->lifted()->create(['listing_id' => $listing->id]);

    $removal = app(RemoveListing::class)($listing, ListingRemovalKind::Temporary, 'Repeat offense.');

    expect($removal->reason)->toBe('Repeat offense.')
        ->and(ListingRemoval::count())->toBe(2);
});
