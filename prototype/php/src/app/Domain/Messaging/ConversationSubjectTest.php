<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

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
