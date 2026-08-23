<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Cart\CurrentCart;
use App\Domain\Notifications\RecipientType;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\Order;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use RuntimeException;

/**
 * Shared ground for the storefront pages: the visitor behind the request, the
 * counts the header carries on every page, and the ownership rule that keeps
 * one customer out of another's orders.
 */
abstract class ShopController extends Controller
{
    public function __construct(protected readonly CurrentCart $currentCart) {}

    protected function visitor(): Customer
    {
        return customer() ?? throw new RuntimeException('The storefront runs behind the customer.identity middleware.');
    }

    protected function now(): DateTimeImmutable
    {
        return now()->toDateTimeImmutable();
    }

    /**
     * @param  view-string  $view
     * @param  array<string, mixed>  $data
     */
    protected function page(string $view, array $data = []): View
    {
        $visitor = $this->visitor();

        return view($view, $data + [
            'cartItemCount' => (int) ($this->currentCart)($visitor)->items()->sum('quantity'),
            'unreadNotificationCount' => Notification::query()
                ->for(RecipientType::Customer, $visitor->id)
                ->unread()
                ->count(),
        ]);
    }

    /**
     * Someone else's order is not theirs to read, pay, or receive, and saying
     * "not found" tells them nothing about whether it exists.
     */
    protected function orderOfVisitor(Order $order): Order
    {
        abort_unless($order->customer_id === $this->visitor()->id, 404);

        return $order;
    }
}
