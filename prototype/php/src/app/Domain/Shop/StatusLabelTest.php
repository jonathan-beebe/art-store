<?php

declare(strict_types=1);

namespace App\Domain\Shop;

it('reads a stored status as a sentence', function (string $stored, string $expected): void {
    expect(StatusLabel::humanize($stored))->toBe($expected);
})->with([
    'pending_verification' => ['pending_verification', 'Pending verification'],
    'paid' => ['paid', 'Paid'],
]);
