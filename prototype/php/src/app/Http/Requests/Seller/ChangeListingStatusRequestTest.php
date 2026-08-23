<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Listings\ListingStatus;

it('rejects a status change the lifecycle does not allow', function (ListingStatus $initial, string $attempted): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['status' => $initial]);

    $response = $this->actingAs($seller, 'seller')
        ->post("/seller/listings/{$listing->id}/status", ['status' => $attempted]);

    $response->assertSessionHasErrors('status');
    expect($listing->refresh()->status)->toBe($initial);
})->with([
    'a transition the lifecycle does not allow' => [ListingStatus::Draft, 'sold'],
    'a status that is not a listing status at all' => [ListingStatus::Draft, 'on_fire'],
    'no status at all' => [ListingStatus::Draft, ''],
    'no transition out of archived' => [ListingStatus::Archived, 'for_sale'],
    'not even a repeat of the status it already has' => [ListingStatus::Archived, 'archived'],
]);

it('answers another sellers listing before it reads the status', function (): void {
    $listing = $this->listing($this->seller('Other Studio'), ['status' => ListingStatus::Draft]);

    $response = $this->actingAs($this->seller(), 'seller')
        ->post("/seller/listings/{$listing->id}/status", ['status' => 'on_fire']);

    $response->assertNotFound();
    $response->assertSessionHasNoErrors();
    expect($listing->refresh()->status)->toBe(ListingStatus::Draft);
});
