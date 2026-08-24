<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domain\Listings\ListingRemovalKind;

it('refuses a submission with no kind or no reason', function (array $form, string $field): void {
    $listing = $this->listing($this->seller());

    $response = $this->actingAs($this->admin(), 'admin')
        ->post("/admin/listings/{$listing->id}/removals", $form);

    $response->assertSessionHasErrors($field);
})->with([
    'no kind' => [['kind' => '', 'reason' => 'Under review.'], 'kind'],
    'not a real kind' => [['kind' => 'forever', 'reason' => 'Under review.'], 'kind'],
    'no reason' => [['kind' => 'temporary', 'reason' => ''], 'reason'],
    'a reason longer than the column' => [['kind' => 'temporary', 'reason' => str_repeat('a', 501)], 'reason'],
]);

it('reads the kind and reason the admin submitted', function (): void {
    $request = RemoveListingRequest::create(
        '/admin/listings/lst_1/removals',
        'POST',
        ['kind' => 'permanent', 'reason' => 'Counterfeit.'],
    );

    expect($request->kind())->toBe(ListingRemovalKind::Permanent)
        ->and($request->reason())->toBe('Counterfeit.');
});
