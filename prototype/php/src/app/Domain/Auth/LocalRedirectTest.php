<?php

declare(strict_types=1);

namespace App\Domain\Auth;

it('resolves a requested target', function (?string $requested, string $expected): void {
    $origin = 'http://localhost:8000';
    $fallback = '/account';

    expect(LocalRedirect::resolve($requested, ActorType::Customer, $fallback, $origin))->toBe($expected);
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
    'the seller portal falls back for a customer' => ['/seller', '/account'],
    'a path inside the seller portal falls back for a customer' => ['/seller/orders/1', '/account'],
    'an absolute seller url falls back for a customer' => ['http://localhost:8000/seller/listings', '/account'],
    'a path that only prefixes the seller portal is kept' => ['/sellers-guide', '/sellers-guide'],
    'the admin site falls back for a customer' => ['/admin', '/account'],
    'a path inside the admin site falls back for a customer' => ['/admin/customers/1', '/account'],
]);

it('keeps a seller on the seller portal', function (): void {
    expect(LocalRedirect::resolve('/seller/orders/1', ActorType::Seller, '/dashboard', 'http://localhost:8000'))
        ->toBe('/seller/orders/1');
});

it('falls back for a seller sent to the admin site', function (): void {
    expect(LocalRedirect::resolve('/admin/customers/1', ActorType::Seller, '/dashboard', 'http://localhost:8000'))
        ->toBe('/dashboard');
});

it('keeps an admin on the admin site', function (): void {
    expect(LocalRedirect::resolve('/admin/customers/1', ActorType::Admin, '/admin', 'http://localhost:8000'))
        ->toBe('/admin/customers/1');
});

it('falls back for an admin sent to the seller portal', function (): void {
    expect(LocalRedirect::resolve('/seller/orders/1', ActorType::Admin, '/admin', 'http://localhost:8000'))
        ->toBe('/admin');
});

it('keeps a local target on its own', function (): void {
    expect(LocalRedirect::keepIfLocal('/checkout', 'http://localhost:8000'))->toBe('/checkout');
});

it('drops a foreign target on its own', function (): void {
    expect(LocalRedirect::keepIfLocal('http://evil.example/steal', 'http://localhost:8000'))->toBeNull();
});
