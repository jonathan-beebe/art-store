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
