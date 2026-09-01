<?php

declare(strict_types=1);

namespace App\Support;

it('redacts a value shaped like an email address', function (): void {
    expect(DataRedaction::redact(['q' => 'harry@hogwarts.example']))
        ->toBe(['q' => '[redacted]']);
});

it('redacts a value shaped like a magic-link token', function (): void {
    $token = str_repeat('a1b2', 20);

    expect(DataRedaction::redact(['token' => $token]))
        ->toBe(['token' => '[redacted]']);
});

it('redacts a value shaped like a card number, spaces and dashes included', function (string $value): void {
    expect(DataRedaction::redact(['q' => $value]))->toBe(['q' => '[redacted]']);
})->with([
    'bare digits' => ['4242424242424242'],
    'spaced' => ['4242 4242 4242 4242'],
    'dashed' => ['4242-4242-4242-4242'],
    'the short end of the range' => ['4242424242424'],
]);

it('leaves a 12-digit number alone, one short of the card-number range', function (): void {
    expect(DataRedaction::redact(['q' => '424242424242']))
        ->toBe(['q' => '424242424242']);
});

it('redacts a 19-digit number, the long end of the card-number range', function (): void {
    expect(DataRedaction::redact(['q' => '4242424242424242424']))
        ->toBe(['q' => '[redacted]']);
});

it('leaves a 20-digit number alone, one over the card-number range', function (): void {
    expect(DataRedaction::redact(['q' => '42424242424242424242']))
        ->toBe(['q' => '42424242424242424242']);
});

it('leaves an ordinary value alone', function (): void {
    expect(DataRedaction::redact(['medium' => 'ceramic', 'q' => 'cup', 'page' => '2']))
        ->toBe(['medium' => 'ceramic', 'q' => 'cup', 'page' => '2']);
});

it('leaves a non-string value alone', function (): void {
    expect(DataRedaction::redact(['count' => 3, 'on' => true, 'missing' => null]))
        ->toBe(['count' => 3, 'on' => true, 'missing' => null]);
});

it('redacts inside a nested array, keeping its shape', function (): void {
    expect(DataRedaction::redact(['filters' => ['owner' => 'harry@hogwarts.example', 'status' => 'active']]))
        ->toBe(['filters' => ['owner' => '[redacted]', 'status' => 'active']]);
});

it('redacts inside a list value the same way', function (): void {
    expect(DataRedaction::redact(['tags' => ['ceramic', 'harry@hogwarts.example']]))
        ->toBe(['tags' => ['ceramic', '[redacted]']]);
});
