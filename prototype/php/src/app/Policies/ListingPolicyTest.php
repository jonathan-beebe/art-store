<?php

declare(strict_types=1);

namespace App\Policies;

it('lets a seller act on their own listing', function (string $ability): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    expect((new ListingPolicy)->{$ability}($seller, $listing)->allowed())->toBeTrue();
})->with(['view', 'update']);

it('answers not found for another sellers listing', function (string $ability): void {
    $listing = $this->listing($this->seller('Other Studio'));

    $response = (new ListingPolicy)->{$ability}($this->seller(), $listing);

    expect($response->denied())->toBeTrue()
        ->and($response->status())->toBe(404);
})->with(['view', 'update']);
