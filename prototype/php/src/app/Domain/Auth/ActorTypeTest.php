<?php

declare(strict_types=1);

namespace App\Domain\Auth;

it('names its own guard per actor', function (ActorType $actor, string $guard): void {
    expect($actor->guard())->toBe($guard);
})->with([
    'seller' => [ActorType::Seller, 'seller'],
    'customer' => [ActorType::Customer, 'customer'],
]);

it('lands on its own site per actor', function (ActorType $actor, string $routeName): void {
    expect($actor->homeRouteName())->toBe($routeName);
})->with([
    'seller' => [ActorType::Seller, 'seller.dashboard'],
    'customer' => [ActorType::Customer, 'shop.account'],
]);

it('signs in on its own site per actor', function (ActorType $actor, string $routeName): void {
    expect($actor->loginRouteName())->toBe($routeName);
})->with([
    'seller' => [ActorType::Seller, 'auth.seller.login'],
    'customer' => [ActorType::Customer, 'auth.customer.login'],
]);

it('answers which paths each actor belongs on', function (ActorType $actor, string $path, bool $allowed): void {
    expect($actor->allowsPath($path))->toBe($allowed);
})->with([
    'a seller on the portal root' => [ActorType::Seller, '/seller', true],
    'a seller inside the portal' => [ActorType::Seller, '/seller/orders/1', true],
    'a seller on the storefront' => [ActorType::Seller, '/orders/1', true],
    'a customer on the portal root' => [ActorType::Customer, '/seller', false],
    'a customer inside the portal' => [ActorType::Customer, '/seller/orders/1', false],
    'a customer on a path that only prefixes the portal' => [ActorType::Customer, '/sellers-guide', true],
    'a customer on the storefront' => [ActorType::Customer, '/orders/1', true],
]);
