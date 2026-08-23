<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Actions\Cart\AddToCart;
use App\Domain\Notifications\NotificationMessage;
use App\Domain\Notifications\RecipientType;
use App\Models\Notification;

it('counts the items the visitor is carrying', function (): void {
    $visitor = $this->visitor();
    app(AddToCart::class)(
        $visitor->currentCart(),
        $this->listing($this->seller(), ['quantity' => 3]),
        2,
        $this->moment('2026-08-20 08:00:00'),
    );

    $response = $this->get('/');

    $response->assertSee('Cart (2)');
});

it('counts the notifications the visitor has not read', function (): void {
    $visitor = $this->arriveAs($this->verifiedCustomer());
    Notification::to(RecipientType::Customer, $visitor->id, NotificationMessage::orderShipped(1, 'Royal Mail', 'RM1'));

    $response = $this->actingAs($visitor, 'customer')->get('/');

    $response->assertSee('Account (1)');
});

it('carries the counts onto every storefront page without the controller passing them', function (): void {
    $this->visitor();

    $this->get('/cart')->assertSee('Cart (0)');
    $this->get('/favorites')->assertSee('Cart (0)');
    $this->get('/orders')->assertSee('Cart (0)');
});

it('renders a page with no storefront visitor without the counts', function (): void {
    $response = $this->get('/login');

    $response->assertOk();
    $response->assertDontSee('Cart (');
});
