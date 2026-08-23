<?php

declare(strict_types=1);

namespace App\Domain\Auth;

it('normalizes an address', function (string $input): void {
    expect(EmailNormalizer::normalize($input))->toBe('artist@example.com');
})->with([
    'lowercases' => ['Artist@Example.COM'],
    'trims surrounding whitespace' => ["  artist@example.com\n"],
    'leaves an already normal address alone' => ['artist@example.com'],
]);
