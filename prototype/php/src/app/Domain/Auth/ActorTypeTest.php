<?php

declare(strict_types=1);

namespace App\Domain\Auth;

it('names its own guard per actor', function (ActorType $actor, string $guard): void {
    expect($actor->guard())->toBe($guard);
})->with([
    'seller' => [ActorType::Seller, 'seller'],
    'customer' => [ActorType::Customer, 'customer'],
    'admin' => [ActorType::Admin, 'admin'],
]);

it('lands on its own site per actor', function (ActorType $actor, string $routeName): void {
    expect($actor->homeRouteName())->toBe($routeName);
})->with([
    'seller' => [ActorType::Seller, 'seller.dashboard'],
    'customer' => [ActorType::Customer, 'shop.account'],
    'admin' => [ActorType::Admin, 'admin.dashboard'],
]);

it('signs in on its own site per actor', function (ActorType $actor, string $routeName): void {
    expect($actor->loginRouteName())->toBe($routeName);
})->with([
    'seller' => [ActorType::Seller, 'auth.seller.login'],
    'customer' => [ActorType::Customer, 'auth.customer.login'],
    'admin' => [ActorType::Admin, 'auth.admin.login'],
]);

it('names the conversations column holding its participant id', function (ActorType $actor, string $column): void {
    expect($actor->participantColumn())->toBe($column);
})->with([
    'seller' => [ActorType::Seller, 'seller_id'],
    'customer' => [ActorType::Customer, 'customer_id'],
    'admin' => [ActorType::Admin, 'admin_id'],
]);

it('reads a posted message on its own site', function (ActorType $actor, string $routeName): void {
    expect($actor->conversationRouteName())->toBe($routeName);
})->with([
    'seller' => [ActorType::Seller, 'seller.messages.show'],
    'customer' => [ActorType::Customer, 'shop.messages.show'],
    'admin' => [ActorType::Admin, 'admin.messages.show'],
]);

it('reads its inbox on its own site', function (ActorType $actor, string $routeName): void {
    expect($actor->inboxRouteName())->toBe($routeName);
})->with([
    'seller' => [ActorType::Seller, 'seller.messages.index'],
    'customer' => [ActorType::Customer, 'shop.messages.index'],
    'admin' => [ActorType::Admin, 'admin.messages.index'],
]);

it('answers which paths each actor belongs on', function (ActorType $actor, string $path, bool $allowed): void {
    expect($actor->allowsPath($path))->toBe($allowed);
})->with([
    'a seller on the portal root' => [ActorType::Seller, '/seller', true],
    'a seller inside the portal' => [ActorType::Seller, '/seller/orders/1', true],
    'a seller on the storefront' => [ActorType::Seller, '/orders/1', true],
    'a seller on the admin site' => [ActorType::Seller, '/admin', false],
    'a seller inside the admin site' => [ActorType::Seller, '/admin/customers/1', false],
    'a customer on the portal root' => [ActorType::Customer, '/seller', false],
    'a customer inside the portal' => [ActorType::Customer, '/seller/orders/1', false],
    'a customer on a path that only prefixes the portal' => [ActorType::Customer, '/sellers-guide', true],
    'a customer on the storefront' => [ActorType::Customer, '/orders/1', true],
    'a customer on the admin site' => [ActorType::Customer, '/admin', false],
    'a customer on a path that only prefixes the admin site' => [ActorType::Customer, '/administrivia', true],
    'an admin on the admin site root' => [ActorType::Admin, '/admin', true],
    'an admin inside the admin site' => [ActorType::Admin, '/admin/customers/1', true],
    'an admin on the storefront' => [ActorType::Admin, '/orders/1', true],
    'an admin on the seller portal' => [ActorType::Admin, '/seller', false],
    'an admin inside the seller portal' => [ActorType::Admin, '/seller/orders/1', false],
    'an admin on a path that only prefixes the seller portal' => [ActorType::Admin, '/sellers-guide', true],
]);
