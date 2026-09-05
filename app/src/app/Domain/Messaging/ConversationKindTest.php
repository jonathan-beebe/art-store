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

it('names the subject column only for the one kind that finds rather than opens', function (ConversationKind $kind, ?string $column): void {
    expect($kind->subjectColumn())->toBe($column);
})->with([
    'admin_seller has none' => [ConversationKind::AdminSeller, null],
    'admin_customer has none' => [ConversationKind::AdminCustomer, null],
    'fulfillment' => [ConversationKind::Fulfillment, 'fulfillment_id'],
    'listing_question has none' => [ConversationKind::ListingQuestion, null],
]);

it('names the context columns a fresh thread of each kind may carry', function (ConversationKind $kind, array $columns): void {
    expect($kind->contextColumns())->toBe($columns);
})->with([
    'admin_seller' => [ConversationKind::AdminSeller, ['fulfillment_id']],
    'admin_customer' => [ConversationKind::AdminCustomer, ['order_id']],
    'fulfillment' => [ConversationKind::Fulfillment, ['fulfillment_id']],
    'listing_question' => [ConversationKind::ListingQuestion, ['listing_id']],
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

it('opens fresh for every kind but fulfillment', function (ConversationKind $kind, bool $opensFresh): void {
    expect($kind->opensFresh())->toBe($opensFresh);
})->with([
    'admin_seller' => [ConversationKind::AdminSeller, true],
    'admin_customer' => [ConversationKind::AdminCustomer, true],
    'fulfillment' => [ConversationKind::Fulfillment, false],
    'listing_question' => [ConversationKind::ListingQuestion, true],
]);

it('names the two support kinds as the desk', function (ConversationKind $kind, bool $isDesk): void {
    expect($kind->isDesk())->toBe($isDesk);
})->with([
    'admin_seller' => [ConversationKind::AdminSeller, true],
    'admin_customer' => [ConversationKind::AdminCustomer, true],
    'fulfillment' => [ConversationKind::Fulfillment, false],
    'listing_question' => [ConversationKind::ListingQuestion, false],
]);

it('names the side that may resolve each kind', function (ConversationKind $kind, ActorType $actor, bool $resolvableBy): void {
    expect($kind->resolvableBy($actor))->toBe($resolvableBy);
})->with([
    'admin_seller by an admin' => [ConversationKind::AdminSeller, ActorType::Admin, true],
    'admin_seller not by the seller' => [ConversationKind::AdminSeller, ActorType::Seller, false],
    'admin_customer by an admin' => [ConversationKind::AdminCustomer, ActorType::Admin, true],
    'admin_customer not by the customer' => [ConversationKind::AdminCustomer, ActorType::Customer, false],
    'fulfillment by the seller' => [ConversationKind::Fulfillment, ActorType::Seller, true],
    'fulfillment not by the customer' => [ConversationKind::Fulfillment, ActorType::Customer, false],
    'listing_question by the seller' => [ConversationKind::ListingQuestion, ActorType::Seller, true],
    'listing_question not by the customer' => [ConversationKind::ListingQuestion, ActorType::Customer, false],
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

it('names the pill every list of threads wears, and its tint', function (ConversationKind $kind, string $label, string $tint): void {
    expect($kind->tagLabel())->toBe($label)
        ->and($kind->tagTint())->toBe($tint);
})->with([
    'a question' => [ConversationKind::ListingQuestion, 'Question', 'indigo'],
    'an order' => [ConversationKind::Fulfillment, 'Order', 'green'],
    'the seller desk' => [ConversationKind::AdminSeller, 'Support', 'gray'],
    'the customer desk' => [ConversationKind::AdminCustomer, 'Support', 'gray'],
]);
