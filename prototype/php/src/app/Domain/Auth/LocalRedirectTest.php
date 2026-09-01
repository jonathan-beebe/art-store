<?php

declare(strict_types=1);

namespace App\Domain\Auth;

it('resolves a requested target', function (?string $requested, ActorType $actor, string $fallback, string $expected): void {
    $origin = 'http://localhost:8000';

    expect(LocalRedirect::resolve($requested, $actor, $fallback, $origin))->toBe($expected);
})->with([
    'a missing target falls back' => [null, ActorType::Customer, '/account', '/account'],
    'a blank target falls back' => ['   ', ActorType::Customer, '/account', '/account'],
    'a root-relative path is kept' => ['/checkout?step=2', ActorType::Customer, '/account', '/checkout?step=2'],
    'an absolute url on this origin is kept' => ['http://localhost:8000/checkout', ActorType::Customer, '/account', 'http://localhost:8000/checkout'],
    'the origin itself is kept' => ['http://localhost:8000', ActorType::Customer, '/account', 'http://localhost:8000'],
    'another host falls back' => ['http://evil.example/steal', ActorType::Customer, '/account', '/account'],
    'a host that only prefixes this origin falls back' => ['http://localhost:8000.evil.example/steal', ActorType::Customer, '/account', '/account'],
    'a protocol-relative url falls back' => ['//evil.example/steal', ActorType::Customer, '/account', '/account'],
    'a backslash-escaped path falls back' => ['/\\evil.example/steal', ActorType::Customer, '/account', '/account'],
    'a non-leading backslash is kept, not treated as protocol-relative' => ['/foo/\\evil.example', ActorType::Customer, '/account', '/foo/\\evil.example'],
    'a target carrying a newline falls back' => ["/checkout\nSet-Cookie: x=1", ActorType::Customer, '/account', '/account'],
    'a target carrying a bare carriage return falls back' => ["/checkout\rSet-Cookie: x=1", ActorType::Customer, '/account', '/account'],
    'a target carrying a bare tab falls back' => ["/checkout\tSet-Cookie: x=1", ActorType::Customer, '/account', '/account'],
    'an origin-prefixed userinfo bypass attempt falls back' => ['http://localhost:8000@evil.example/steal', ActorType::Customer, '/account', '/account'],
    'an uppercase scheme does not match the lowercase origin, so it falls back' => ['HTTP://localhost:8000/checkout', ActorType::Customer, '/account', '/account'],
    'the seller portal falls back for a customer' => ['/seller', ActorType::Customer, '/account', '/account'],
    'a path inside the seller portal falls back for a customer' => ['/seller/orders/1', ActorType::Customer, '/account', '/account'],
    'an absolute seller url falls back for a customer' => ['http://localhost:8000/seller/listings', ActorType::Customer, '/account', '/account'],
    'a path that only prefixes the seller portal is kept' => ['/sellers-guide', ActorType::Customer, '/account', '/sellers-guide'],
    'the admin site falls back for a customer' => ['/admin', ActorType::Customer, '/account', '/account'],
    'a path inside the admin site falls back for a customer' => ['/admin/customers/1', ActorType::Customer, '/account', '/account'],
    'keeps a seller on the seller portal' => ['/seller/orders/1', ActorType::Seller, '/dashboard', '/seller/orders/1'],
    'falls back for a seller sent to the admin site' => ['/admin/customers/1', ActorType::Seller, '/dashboard', '/dashboard'],
    'keeps an admin on the admin site' => ['/admin/customers/1', ActorType::Admin, '/admin', '/admin/customers/1'],
    'falls back for an admin sent to the seller portal' => ['/seller/orders/1', ActorType::Admin, '/admin', '/admin'],
]);

it('keeps a local target on its own', function (): void {
    expect(LocalRedirect::keepIfLocal('/checkout', 'http://localhost:8000'))->toBe('/checkout');
});

it('drops a foreign target on its own', function (): void {
    expect(LocalRedirect::keepIfLocal('http://evil.example/steal', 'http://localhost:8000'))->toBeNull();
});
