<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

it('labels every case', function (ActorKind $kind, string $label): void {
    expect($kind->label())->toBe($label);
})->with([
    'verified' => [ActorKind::Verified, 'verified'],
    'anonymous' => [ActorKind::Anonymous, 'anonymous'],
]);
