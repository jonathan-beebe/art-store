<?php

declare(strict_types=1);

namespace App\Domain\Listings;

it('reads its stored value back as a sentence', function (ListingRemovalKind $kind, string $expected): void {
    expect($kind->label())->toBe($expected);
})->with([
    'temporary' => [ListingRemovalKind::Temporary, 'Temporary'],
    'permanent' => [ListingRemovalKind::Permanent, 'Permanent'],
]);

it('only a temporary removal may be lifted', function (ListingRemovalKind $kind, bool $expected): void {
    expect($kind->canLift())->toBe($expected);
})->with([
    'temporary lifts' => [ListingRemovalKind::Temporary, true],
    'permanent does not' => [ListingRemovalKind::Permanent, false],
]);
