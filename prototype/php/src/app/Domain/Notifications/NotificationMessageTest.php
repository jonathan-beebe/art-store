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

it('hands an inbox row its subject, body, and url', function (): void {
    expect(NotificationMessage::orderShipped('ord_00000000000000000000000001', 'USPS', '94001')->toArray())->toBe([
        'subject' => 'Order shipped',
        'body' => 'Order ord_00000000000000000000000001 shipped with USPS. Tracking number 94001.',
        'url' => null,
    ]);
});
