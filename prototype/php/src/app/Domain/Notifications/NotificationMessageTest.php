<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use App\Domain\Money\Money;

it('tells the seller what is held for them on a sale', function (): void {
    $message = NotificationMessage::itemSold(12, Money::fromCents(9000));

    expect($message->subject)->toBe('Item sold')
        ->and($message->body)->toContain('Order #12')
        ->and($message->body)->toContain('$90.00')
        ->and($message->url)->toBeNull();
});

it('tells the customer who is carrying a shipment', function (): void {
    $message = NotificationMessage::orderShipped(12, 'USPS', '9400111899');

    expect($message->subject)->toBe('Order shipped')
        ->and($message->body)->toContain('Order #12')
        ->and($message->body)->toContain('USPS')
        ->and($message->body)->toContain('9400111899');
});

it('hands an inbox row its subject, body, and url', function (): void {
    expect(NotificationMessage::orderShipped(4, 'USPS', '94001')->toArray())->toBe([
        'subject' => 'Order shipped',
        'body' => 'Order #4 shipped with USPS. Tracking number 94001.',
        'url' => null,
    ]);
});
