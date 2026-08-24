<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Domain\DomainRuleViolation;
use App\Models\ListingRemoval;

it('lifts the active temporary removal', function (): void {
    $listing = $this->listing($this->seller());
    ListingRemoval::factory()->create(['listing_id' => $listing->id]);

    $removal = app(LiftListingRemoval::class)($listing, $this->moment('2026-08-23 10:00:00'));

    expect($removal->lifted_at?->format('Y-m-d H:i:s'))->toBe('2026-08-23 10:00:00')
        ->and($listing->hasActiveRemoval())->toBeFalse();
});

it('refuses a listing with no active removal', function (): void {
    $listing = $this->listing($this->seller());

    $lift = fn () => app(LiftListingRemoval::class)($listing, $this->moment('2026-08-23 10:00:00'));

    expect($lift)->toThrow(DomainRuleViolation::class, 'This listing has no active removal.');
});

it('refuses a listing whose removal was already lifted', function (): void {
    $listing = $this->listing($this->seller());
    ListingRemoval::factory()->lifted()->create(['listing_id' => $listing->id]);

    $lift = fn () => app(LiftListingRemoval::class)($listing, $this->moment('2026-08-23 10:00:00'));

    expect($lift)->toThrow(DomainRuleViolation::class, 'This listing has no active removal.');
});

it('refuses to lift a permanent removal', function (): void {
    $listing = $this->listing($this->seller());
    ListingRemoval::factory()->permanent()->create(['listing_id' => $listing->id]);

    $lift = fn () => app(LiftListingRemoval::class)($listing, $this->moment('2026-08-23 10:00:00'));

    expect($lift)->toThrow(DomainRuleViolation::class, 'A permanent removal cannot be lifted.')
        ->and($listing->hasActiveRemoval())->toBeTrue();
});
