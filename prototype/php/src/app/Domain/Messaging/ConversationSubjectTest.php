<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

use InvalidArgumentException;

const ADMIN_ID = 'adm_00000000000000000000000001';
const SELLER_ID = 'sel_00000000000000000000000005';
const CUSTOMER_ID = 'cus_00000000000000000000000009';
const FULFILLMENT_ID = 'ful_00000000000000000000000012';
const LISTING_ID = 'lst_00000000000000000000000024';

it('keys an admin/seller support thread by both participants', function (): void {
    $subject = ConversationSubject::adminSeller(ADMIN_ID, SELLER_ID);

    expect($subject->subjectKey())->toBe('admin_seller:a'.ADMIN_ID.':s'.SELLER_ID)
        ->and($subject->columns())->toBe([
            'kind' => 'admin_seller',
            'admin_id' => ADMIN_ID,
            'seller_id' => SELLER_ID,
        ]);
});

it('keys an admin/customer support thread by both participants', function (): void {
    $subject = ConversationSubject::adminCustomer(ADMIN_ID, CUSTOMER_ID);

    expect($subject->subjectKey())->toBe('admin_customer:a'.ADMIN_ID.':c'.CUSTOMER_ID)
        ->and($subject->columns())->toBe([
            'kind' => 'admin_customer',
            'admin_id' => ADMIN_ID,
            'customer_id' => CUSTOMER_ID,
        ]);
});

it('keys a fulfillment thread by both participants and the order it is about', function (): void {
    $subject = ConversationSubject::fulfillment(SELLER_ID, CUSTOMER_ID, FULFILLMENT_ID);

    expect($subject->subjectKey())->toBe('fulfillment:s'.SELLER_ID.':c'.CUSTOMER_ID.':f'.FULFILLMENT_ID)
        ->and($subject->columns())->toBe([
            'kind' => 'fulfillment',
            'seller_id' => SELLER_ID,
            'customer_id' => CUSTOMER_ID,
            'fulfillment_id' => FULFILLMENT_ID,
        ]);
});

it('keys a listing question by both participants and the listing it is about', function (): void {
    $subject = ConversationSubject::listingQuestion(SELLER_ID, CUSTOMER_ID, LISTING_ID);

    expect($subject->subjectKey())->toBe('listing_question:s'.SELLER_ID.':c'.CUSTOMER_ID.':l'.LISTING_ID)
        ->and($subject->columns())->toBe([
            'kind' => 'listing_question',
            'seller_id' => SELLER_ID,
            'customer_id' => CUSTOMER_ID,
            'listing_id' => LISTING_ID,
        ]);
});

it('names its kind', function (): void {
    expect(ConversationSubject::fulfillment(SELLER_ID, CUSTOMER_ID, FULFILLMENT_ID)->kind)
        ->toBe(ConversationKind::Fulfillment);
});

it('rebuilds an admin/seller key from the row\'s own columns', function (): void {
    $ids = ['admin_id' => ADMIN_ID, 'seller_id' => SELLER_ID, 'customer_id' => null, 'listing_id' => null, 'fulfillment_id' => null];

    expect(ConversationSubject::for(ConversationKind::AdminSeller, $ids)->subjectKey())
        ->toBe('admin_seller:a'.ADMIN_ID.':s'.SELLER_ID);
});

it('rebuilds an admin/customer key from the row\'s own columns', function (): void {
    $ids = ['admin_id' => ADMIN_ID, 'customer_id' => CUSTOMER_ID, 'seller_id' => null, 'listing_id' => null, 'fulfillment_id' => null];

    expect(ConversationSubject::for(ConversationKind::AdminCustomer, $ids)->subjectKey())
        ->toBe('admin_customer:a'.ADMIN_ID.':c'.CUSTOMER_ID);
});

it('rebuilds a fulfillment key from the row\'s own columns', function (): void {
    $ids = ['seller_id' => SELLER_ID, 'customer_id' => CUSTOMER_ID, 'fulfillment_id' => FULFILLMENT_ID, 'admin_id' => null, 'listing_id' => null];

    expect(ConversationSubject::for(ConversationKind::Fulfillment, $ids)->subjectKey())
        ->toBe('fulfillment:s'.SELLER_ID.':c'.CUSTOMER_ID.':f'.FULFILLMENT_ID);
});

it('rebuilds a listing question key from the row\'s own columns', function (): void {
    $ids = ['seller_id' => SELLER_ID, 'customer_id' => CUSTOMER_ID, 'listing_id' => LISTING_ID, 'admin_id' => null, 'fulfillment_id' => null];

    expect(ConversationSubject::for(ConversationKind::ListingQuestion, $ids)->subjectKey())
        ->toBe('listing_question:s'.SELLER_ID.':c'.CUSTOMER_ID.':l'.LISTING_ID);
});

it('refuses to rebuild a subject missing a participant', function (): void {
    ConversationSubject::for(ConversationKind::ListingQuestion, ['seller_id' => SELLER_ID, 'listing_id' => LISTING_ID]);
})->throws(InvalidArgumentException::class, 'A listing_question conversation names a customer_id.');

it('refuses to rebuild a subject missing the row it is about', function (): void {
    ConversationSubject::for(ConversationKind::Fulfillment, ['seller_id' => SELLER_ID, 'customer_id' => CUSTOMER_ID]);
})->throws(InvalidArgumentException::class, 'A fulfillment conversation names a fulfillment_id.');
