<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

use InvalidArgumentException;

const ADMIN_ID = 'adm_00000000000000000000000001';
const SELLER_ID = 'sel_00000000000000000000000005';
const CUSTOMER_ID = 'cus_00000000000000000000000009';
const FULFILLMENT_ID = 'ful_00000000000000000000000012';
const LISTING_ID = 'lst_00000000000000000000000024';

it('keys a conversation by its kind and participants', function (ConversationSubject $subject, string $expectedKey, array $expectedColumns): void {
    expect($subject->subjectKey())->toBe($expectedKey)
        ->and($subject->columns())->toBe($expectedColumns);
})->with([
    'admin/seller support thread' => [
        ConversationSubject::adminSeller(ADMIN_ID, SELLER_ID),
        'admin_seller:a'.ADMIN_ID.':s'.SELLER_ID,
        ['kind' => 'admin_seller', 'admin_id' => ADMIN_ID, 'seller_id' => SELLER_ID],
    ],
    'admin/customer support thread' => [
        ConversationSubject::adminCustomer(ADMIN_ID, CUSTOMER_ID),
        'admin_customer:a'.ADMIN_ID.':c'.CUSTOMER_ID,
        ['kind' => 'admin_customer', 'admin_id' => ADMIN_ID, 'customer_id' => CUSTOMER_ID],
    ],
    'fulfillment thread, keyed by both participants and the order it is about' => [
        ConversationSubject::fulfillment(SELLER_ID, CUSTOMER_ID, FULFILLMENT_ID),
        'fulfillment:s'.SELLER_ID.':c'.CUSTOMER_ID.':f'.FULFILLMENT_ID,
        ['kind' => 'fulfillment', 'seller_id' => SELLER_ID, 'customer_id' => CUSTOMER_ID, 'fulfillment_id' => FULFILLMENT_ID],
    ],
    'listing question, keyed by both participants and the listing it is about' => [
        ConversationSubject::listingQuestion(SELLER_ID, CUSTOMER_ID, LISTING_ID),
        'listing_question:s'.SELLER_ID.':c'.CUSTOMER_ID.':l'.LISTING_ID,
        ['kind' => 'listing_question', 'seller_id' => SELLER_ID, 'customer_id' => CUSTOMER_ID, 'listing_id' => LISTING_ID],
    ],
]);

it('names its kind', function (): void {
    expect(ConversationSubject::fulfillment(SELLER_ID, CUSTOMER_ID, FULFILLMENT_ID)->kind)
        ->toBe(ConversationKind::Fulfillment);
});

it('rebuilds a subject key from the row\'s own columns',
    /** @param array<string, string|null> $ids */
    function (ConversationKind $kind, array $ids, string $expectedKey): void {
        // The dataset rows below are column => id maps of the right shape; the
        // closure signature cannot carry generics for the analyser to see.
        // @phpstan-ignore-next-line
        expect(ConversationSubject::for($kind, $ids)->subjectKey())->toBe($expectedKey);
    })->with([
        'admin/seller' => [
            ConversationKind::AdminSeller,
            ['admin_id' => ADMIN_ID, 'seller_id' => SELLER_ID, 'customer_id' => null, 'listing_id' => null, 'fulfillment_id' => null],
            'admin_seller:a'.ADMIN_ID.':s'.SELLER_ID,
        ],
        'admin/customer' => [
            ConversationKind::AdminCustomer,
            ['admin_id' => ADMIN_ID, 'customer_id' => CUSTOMER_ID, 'seller_id' => null, 'listing_id' => null, 'fulfillment_id' => null],
            'admin_customer:a'.ADMIN_ID.':c'.CUSTOMER_ID,
        ],
        'fulfillment' => [
            ConversationKind::Fulfillment,
            ['seller_id' => SELLER_ID, 'customer_id' => CUSTOMER_ID, 'fulfillment_id' => FULFILLMENT_ID, 'admin_id' => null, 'listing_id' => null],
            'fulfillment:s'.SELLER_ID.':c'.CUSTOMER_ID.':f'.FULFILLMENT_ID,
        ],
        'listing question' => [
            ConversationKind::ListingQuestion,
            ['seller_id' => SELLER_ID, 'customer_id' => CUSTOMER_ID, 'listing_id' => LISTING_ID, 'admin_id' => null, 'fulfillment_id' => null],
            'listing_question:s'.SELLER_ID.':c'.CUSTOMER_ID.':l'.LISTING_ID,
        ],
    ]);

it('refuses to rebuild a subject missing a participant', function (): void {
    ConversationSubject::for(ConversationKind::ListingQuestion, ['seller_id' => SELLER_ID, 'listing_id' => LISTING_ID]);
})->throws(InvalidArgumentException::class, 'A listing_question conversation names a customer_id.');

it('refuses to rebuild a subject missing the row it is about', function (): void {
    ConversationSubject::for(ConversationKind::Fulfillment, ['seller_id' => SELLER_ID, 'customer_id' => CUSTOMER_ID]);
})->throws(InvalidArgumentException::class, 'A fulfillment conversation names a fulfillment_id.');
