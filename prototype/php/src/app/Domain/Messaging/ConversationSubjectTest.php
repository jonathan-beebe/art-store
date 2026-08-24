<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

use InvalidArgumentException;

it('keys an admin/seller support thread by both participants', function (): void {
    $subject = ConversationSubject::adminSeller(1, 5);

    expect($subject->subjectKey())->toBe('admin_seller:a1:s5')
        ->and($subject->columns())->toBe(['kind' => 'admin_seller', 'admin_id' => 1, 'seller_id' => 5]);
});

it('keys an admin/customer support thread by both participants', function (): void {
    $subject = ConversationSubject::adminCustomer(1, 9);

    expect($subject->subjectKey())->toBe('admin_customer:a1:c9')
        ->and($subject->columns())->toBe(['kind' => 'admin_customer', 'admin_id' => 1, 'customer_id' => 9]);
});

it('keys a fulfillment thread by both participants and the order it is about', function (): void {
    $subject = ConversationSubject::fulfillment(3, 9, 12);

    expect($subject->subjectKey())->toBe('fulfillment:s3:c9:f12')
        ->and($subject->columns())->toBe([
            'kind' => 'fulfillment',
            'seller_id' => 3,
            'customer_id' => 9,
            'fulfillment_id' => 12,
        ]);
});

it('keys a listing question by both participants and the listing it is about', function (): void {
    $subject = ConversationSubject::listingQuestion(3, 9, 24);

    expect($subject->subjectKey())->toBe('listing_question:s3:c9:l24')
        ->and($subject->columns())->toBe([
            'kind' => 'listing_question',
            'seller_id' => 3,
            'customer_id' => 9,
            'listing_id' => 24,
        ]);
});

it('names its kind', function (): void {
    expect(ConversationSubject::fulfillment(1, 2, 3)->kind)->toBe(ConversationKind::Fulfillment);
});

it('rebuilds an admin/seller key from the row\'s own columns', function (): void {
    $ids = ['admin_id' => 1, 'seller_id' => 5, 'customer_id' => null, 'listing_id' => null, 'fulfillment_id' => null];

    expect(ConversationSubject::for(ConversationKind::AdminSeller, $ids)->subjectKey())->toBe('admin_seller:a1:s5');
});

it('rebuilds an admin/customer key from the row\'s own columns', function (): void {
    $ids = ['admin_id' => 1, 'customer_id' => 9, 'seller_id' => null, 'listing_id' => null, 'fulfillment_id' => null];

    expect(ConversationSubject::for(ConversationKind::AdminCustomer, $ids)->subjectKey())->toBe('admin_customer:a1:c9');
});

it('rebuilds a fulfillment key from the row\'s own columns', function (): void {
    $ids = ['seller_id' => 3, 'customer_id' => 9, 'fulfillment_id' => 12, 'admin_id' => null, 'listing_id' => null];

    expect(ConversationSubject::for(ConversationKind::Fulfillment, $ids)->subjectKey())->toBe('fulfillment:s3:c9:f12');
});

it('rebuilds a listing question key from the row\'s own columns', function (): void {
    $ids = ['seller_id' => 3, 'customer_id' => 9, 'listing_id' => 24, 'admin_id' => null, 'fulfillment_id' => null];

    expect(ConversationSubject::for(ConversationKind::ListingQuestion, $ids)->subjectKey())->toBe('listing_question:s3:c9:l24');
});

it('refuses to rebuild a subject missing a participant', function (): void {
    ConversationSubject::for(ConversationKind::ListingQuestion, ['seller_id' => 3, 'listing_id' => 24]);
})->throws(InvalidArgumentException::class, 'A listing_question conversation names a customer_id.');

it('refuses to rebuild a subject missing the row it is about', function (): void {
    ConversationSubject::for(ConversationKind::Fulfillment, ['seller_id' => 3, 'customer_id' => 9]);
})->throws(InvalidArgumentException::class, 'A fulfillment conversation names a fulfillment_id.');
