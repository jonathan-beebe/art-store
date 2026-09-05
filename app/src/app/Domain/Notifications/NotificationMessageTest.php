<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use App\Domain\Money\Money;

it('tells the seller what is held for them on a sale', function (): void {
    $message = NotificationMessage::itemSold('ord_00000000000000000000000001', Money::fromCents(9000));

    expect($message->subject)->toBe('Item sold')
        ->and($message->body)->toContain('Order ord_00000000000000000000000001')
        ->and($message->body)->toContain('$90.00')
        ->and($message->url)->toBeNull();
});

it('tells the customer who is carrying a shipment', function (): void {
    $message = NotificationMessage::orderShipped('ord_00000000000000000000000001', 'USPS', '9400111899');

    expect($message->subject)->toBe('Order shipped')
        ->and($message->body)->toContain('Order ord_00000000000000000000000001')
        ->and($message->body)->toContain('USPS')
        ->and($message->body)->toContain('9400111899');
});

it('tells a participant a thread got a new message', function (): void {
    $message = NotificationMessage::messageReceived('Blue Vase', 'https://example.test/messages/1');

    expect($message->subject)->toBe('New message')
        ->and($message->body)->toBe('You have a new message about Blue Vase.')
        ->and($message->url)->toBe('https://example.test/messages/1');
});

it('leaves the url null when the thread has no route yet', function (): void {
    expect(NotificationMessage::messageReceived('Blue Vase', null)->url)->toBeNull();
});

it('tells the supported side their thread was marked resolved', function (): void {
    $message = NotificationMessage::conversationResolved('Support · Payout timing', 'https://example.test/messages/1');

    expect($message->subject)->toBe('Marked resolved')
        ->and($message->body)->toBe('Your thread about Support · Payout timing was marked resolved.')
        ->and($message->url)->toBe('https://example.test/messages/1');
});

it('leaves the resolved url null when the thread has no route yet', function (): void {
    expect(NotificationMessage::conversationResolved('Support · Payout timing', null)->url)->toBeNull();
});

it('hands an inbox row its subject, body, and url', function (): void {
    expect(NotificationMessage::orderShipped('ord_00000000000000000000000001', 'USPS', '94001')->toArray())->toBe([
        'subject' => 'Order shipped',
        'body' => 'Order ord_00000000000000000000000001 shipped with USPS. Tracking number 94001.',
        'url' => null,
    ]);
});

it('tells the customer their order was cancelled before it was charged', function (): void {
    $message = NotificationMessage::purchaseCancelled('ord_00000000000000000000000001');

    expect($message->toArray())->toBe([
        'subject' => 'Order cancelled',
        'body' => 'Order ord_00000000000000000000000001 was cancelled before it was paid. Nothing has been charged.',
        'url' => null,
    ]);
});

it('tells the seller their pieces are back on the storefront after a cancellation', function (): void {
    $message = NotificationMessage::saleCancelled('ord_00000000000000000000000001');

    expect($message->toArray())->toBe([
        'subject' => 'Order cancelled',
        'body' => 'Order ord_00000000000000000000000001 was cancelled before it was paid. Your pieces are back on the storefront.',
        'url' => null,
    ]);
});

it('tells the customer how much of their order was refunded and why', function (): void {
    $message = NotificationMessage::purchaseRefunded('ord_00000000000000000000000001', Money::fromCents(4500), 'Item arrived damaged.');

    expect($message->toArray())->toBe([
        'subject' => 'Refund issued',
        'body' => '$45.00 of order ord_00000000000000000000000001 was refunded. Reason: Item arrived damaged.',
        'url' => null,
    ]);
});

it('tells the seller how much was refunded on their sale and why', function (): void {
    $message = NotificationMessage::saleRefunded('ord_00000000000000000000000001', Money::fromCents(4500), 'Item arrived damaged.');

    expect($message->toArray())->toBe([
        'subject' => 'Refund issued',
        'body' => 'A refund of $45.00 was issued on order ord_00000000000000000000000001. Reason: Item arrived damaged.',
        'url' => null,
    ]);
});
