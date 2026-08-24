<?php

declare(strict_types=1);

namespace App\Domain\Listings;

it('reads its stored value back as a sentence', function (RemovedFilter $filter, string $expected): void {
    expect($filter->label())->toBe($expected);
})->with([
    'any' => [RemovedFilter::Any, 'Any'],
    'removed' => [RemovedFilter::Removed, 'Removed'],
    'visible' => [RemovedFilter::Visible, 'Visible'],
]);
