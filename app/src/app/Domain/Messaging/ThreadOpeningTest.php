<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

const OPENING_SELLER_ID = 'sel_00000000000000000000000005';
const OPENING_CUSTOMER_ID = 'cus_00000000000000000000000009';
const OPENING_LISTING_ID = 'lst_00000000000000000000000024';
const OPENING_FULFILLMENT_ID = 'ful_00000000000000000000000012';
const OPENING_ORDER_ID = 'ord_00000000000000000000000031';

it('opens an admin/seller thread with no admin named and no context', function (): void {
    $opening = ThreadOpening::adminSeller(OPENING_SELLER_ID, ThreadTitle::of('Payout timing'));

    expect($opening->kind)->toBe(ConversationKind::AdminSeller)
        ->and($opening->columns())->toBe([
            'kind' => 'admin_seller',
            'title' => 'Payout timing',
            'seller_id' => OPENING_SELLER_ID,
        ]);
});

it('opens an admin/seller thread carrying the order it is about', function (): void {
    $opening = ThreadOpening::adminSeller(OPENING_SELLER_ID, ThreadTitle::of('Payout timing'), OPENING_FULFILLMENT_ID);

    expect($opening->columns())->toBe([
        'kind' => 'admin_seller',
        'title' => 'Payout timing',
        'seller_id' => OPENING_SELLER_ID,
        'fulfillment_id' => OPENING_FULFILLMENT_ID,
    ]);
});

it('opens an admin/customer thread with no admin named and no context', function (): void {
    $opening = ThreadOpening::adminCustomer(OPENING_CUSTOMER_ID, ThreadTitle::of('Missing confirmation email'));

    expect($opening->kind)->toBe(ConversationKind::AdminCustomer)
        ->and($opening->columns())->toBe([
            'kind' => 'admin_customer',
            'title' => 'Missing confirmation email',
            'customer_id' => OPENING_CUSTOMER_ID,
        ]);
});

it('opens an admin/customer thread carrying the order it is about', function (): void {
    $opening = ThreadOpening::adminCustomer(OPENING_CUSTOMER_ID, ThreadTitle::of('Missing confirmation email'), OPENING_ORDER_ID);

    expect($opening->columns())->toBe([
        'kind' => 'admin_customer',
        'title' => 'Missing confirmation email',
        'customer_id' => OPENING_CUSTOMER_ID,
        'order_id' => OPENING_ORDER_ID,
    ]);
});

it('opens a listing question thread naming both sides and the listing', function (): void {
    $opening = ThreadOpening::listingQuestion(OPENING_SELLER_ID, OPENING_CUSTOMER_ID, OPENING_LISTING_ID, ThreadTitle::of('Does this ship framed?'));

    expect($opening->kind)->toBe(ConversationKind::ListingQuestion)
        ->and($opening->columns())->toBe([
            'kind' => 'listing_question',
            'title' => 'Does this ship framed?',
            'seller_id' => OPENING_SELLER_ID,
            'customer_id' => OPENING_CUSTOMER_ID,
            'listing_id' => OPENING_LISTING_ID,
        ]);
});
