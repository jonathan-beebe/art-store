<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

use InvalidArgumentException;

const SELLER_ID = 'sel_00000000000000000000000005';
const CUSTOMER_ID = 'cus_00000000000000000000000009';
const FULFILLMENT_ID = 'ful_00000000000000000000000012';

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

it('names its kind', function (): void {
    expect(ConversationSubject::fulfillment(SELLER_ID, CUSTOMER_ID, FULFILLMENT_ID)->kind)
        ->toBe(ConversationKind::Fulfillment);
});

it('rebuilds a subject key from the row\'s own columns', function (): void {
    $ids = ['seller_id' => SELLER_ID, 'customer_id' => CUSTOMER_ID, 'fulfillment_id' => FULFILLMENT_ID];

    expect(ConversationSubject::for(ConversationKind::Fulfillment, $ids)->subjectKey())
        ->toBe('fulfillment:s'.SELLER_ID.':c'.CUSTOMER_ID.':f'.FULFILLMENT_ID);
});

it('refuses to rebuild a subject missing a participant', function (): void {
    ConversationSubject::for(ConversationKind::Fulfillment, ['seller_id' => SELLER_ID, 'fulfillment_id' => FULFILLMENT_ID]);
})->throws(InvalidArgumentException::class, 'A fulfillment conversation names a customer_id.');

it('refuses to rebuild a subject missing the row it is about', function (): void {
    ConversationSubject::for(ConversationKind::Fulfillment, ['seller_id' => SELLER_ID, 'customer_id' => CUSTOMER_ID]);
})->throws(InvalidArgumentException::class, 'A fulfillment conversation names a fulfillment_id.');

it('refuses to rebuild a subject for a kind that opens fresh', function (): void {
    ConversationSubject::for(ConversationKind::ListingQuestion, ['seller_id' => SELLER_ID, 'customer_id' => CUSTOMER_ID]);
})->throws(InvalidArgumentException::class, 'Only a fulfillment conversation carries a subject_key to rebuild.');
