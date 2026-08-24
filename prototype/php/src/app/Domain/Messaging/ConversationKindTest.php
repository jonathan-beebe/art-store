<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

use App\Domain\Auth\ActorType;

it('names the two columns each kind fills', function (ConversationKind $kind, array $columns): void {
    expect($kind->participantColumns())->toBe($columns);
})->with([
    'admin_seller' => [ConversationKind::AdminSeller, ['admin_id', 'seller_id']],
    'admin_customer' => [ConversationKind::AdminCustomer, ['admin_id', 'customer_id']],
    'fulfillment' => [ConversationKind::Fulfillment, ['seller_id', 'customer_id']],
    'listing_question' => [ConversationKind::ListingQuestion, ['seller_id', 'customer_id']],
]);

it('names the subject column only for the two kinds that carry one', function (ConversationKind $kind, ?string $column): void {
    expect($kind->subjectColumn())->toBe($column);
})->with([
    'admin_seller has none' => [ConversationKind::AdminSeller, null],
    'admin_customer has none' => [ConversationKind::AdminCustomer, null],
    'fulfillment' => [ConversationKind::Fulfillment, 'fulfillment_id'],
    'listing_question' => [ConversationKind::ListingQuestion, 'listing_id'],
]);

it('admits only the two participant types a kind fills', function (ConversationKind $kind, ActorType $actor, bool $admitted): void {
    expect($kind->admits($actor))->toBe($admitted);
})->with([
    'admin_seller admits an admin' => [ConversationKind::AdminSeller, ActorType::Admin, true],
    'admin_seller admits a seller' => [ConversationKind::AdminSeller, ActorType::Seller, true],
    'admin_seller refuses a customer' => [ConversationKind::AdminSeller, ActorType::Customer, false],
    'admin_customer admits an admin' => [ConversationKind::AdminCustomer, ActorType::Admin, true],
    'admin_customer admits a customer' => [ConversationKind::AdminCustomer, ActorType::Customer, true],
    'admin_customer refuses a seller' => [ConversationKind::AdminCustomer, ActorType::Seller, false],
    'fulfillment admits a seller' => [ConversationKind::Fulfillment, ActorType::Seller, true],
    'fulfillment admits a customer' => [ConversationKind::Fulfillment, ActorType::Customer, true],
    'fulfillment refuses an admin' => [ConversationKind::Fulfillment, ActorType::Admin, false],
    'listing_question admits a seller' => [ConversationKind::ListingQuestion, ActorType::Seller, true],
    'listing_question admits a customer' => [ConversationKind::ListingQuestion, ActorType::Customer, true],
    'listing_question refuses an admin' => [ConversationKind::ListingQuestion, ActorType::Admin, false],
]);

it('names the desk for the two support kinds', function (ConversationKind $kind): void {
    expect($kind->topic(null, null))->toBe('Support');
})->with([
    'admin_seller' => [ConversationKind::AdminSeller],
    'admin_customer' => [ConversationKind::AdminCustomer],
]);

it('names the order for a fulfillment thread', function (): void {
    expect(ConversationKind::Fulfillment->topic('ord_00000000000000000000000001', null))->toBe('Order ord_00000000000000000000000001');
});

it('names the listing for a listing question', function (): void {
    expect(ConversationKind::ListingQuestion->topic(null, 'Blue Vase'))->toBe('Blue Vase');
});

it('falls back to a plain word when a listing question carries no title', function (): void {
    expect(ConversationKind::ListingQuestion->topic(null, null))->toBe('a listing');
});
