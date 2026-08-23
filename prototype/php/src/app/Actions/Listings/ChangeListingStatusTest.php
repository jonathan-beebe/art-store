<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Domain\Listings\ListingStatus;
use DomainException;

it('moves a listing through an allowed transition', function (ListingStatus $from, ListingStatus $to): void {
    $listing = $this->listing($this->seller(), ['status' => $from]);

    app(ChangeListingStatus::class)($listing, $to);

    expect($listing)->toHaveStatus($to);
})->with([
    'draft to for sale' => [ListingStatus::Draft, ListingStatus::ForSale],
    'for sale to archived' => [ListingStatus::ForSale, ListingStatus::Archived],
]);

it('refuses a transition the lifecycle does not allow', function (): void {
    $listing = $this->listing($this->seller(), ['status' => ListingStatus::Archived]);

    app(ChangeListingStatus::class)($listing, ListingStatus::ForSale);
})->throws(DomainException::class);

it('leaves the row alone when the transition is refused', function (): void {
    $listing = $this->listing($this->seller(), ['status' => ListingStatus::Draft]);

    try {
        app(ChangeListingStatus::class)($listing, ListingStatus::Sold);
    } catch (DomainException) {
        // The assertion below is the point; the throw is asserted above.
    }

    expect($listing)->toHaveStatus(ListingStatus::Draft);
});
