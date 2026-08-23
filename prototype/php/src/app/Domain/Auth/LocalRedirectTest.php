<?php

declare(strict_types=1);

namespace App\Domain\Auth;

it('resolves a requested target', function (?string $requested, string $expected): void {
    $origin = 'http://localhost:8000';
    $fallback = '/account';

    expect(LocalRedirect::resolve($requested, $fallback, $origin))->toBe($expected);
})->with([
    'a missing target falls back' => [null, '/account'],
    'a blank target falls back' => ['   ', '/account'],
    'a root-relative path is kept' => ['/checkout?step=2', '/checkout?step=2'],
    'an absolute url on this origin is kept' => ['http://localhost:8000/checkout', 'http://localhost:8000/checkout'],
    'the origin itself is kept' => ['http://localhost:8000', 'http://localhost:8000'],
    'another host falls back' => ['http://evil.example/steal', '/account'],
    'a host that only prefixes this origin falls back' => ['http://localhost:8000.evil.example/steal', '/account'],
    'a protocol-relative url falls back' => ['//evil.example/steal', '/account'],
    'a backslash-escaped path falls back' => ['/\\evil.example/steal', '/account'],
    'a target carrying a newline falls back' => ["/checkout\nSet-Cookie: x=1", '/account'],
]);

it('keeps a local target on its own', function (): void {
    expect(LocalRedirect::keepIfLocal('/checkout', 'http://localhost:8000'))->toBe('/checkout');
});

it('drops a foreign target on its own', function (): void {
    expect(LocalRedirect::keepIfLocal('http://evil.example/steal', 'http://localhost:8000'))->toBeNull();
});
