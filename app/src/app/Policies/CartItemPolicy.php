<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CartItem;
use App\Models\Customer;
use Illuminate\Auth\Access\Response;

/**
 * A cart line belongs to one customer's one cart. Another customer's line
 * answers "not found", never "forbidden", so an id outside a visitor's own
 * cart is never confirmed to exist.
 */
final class CartItemPolicy
{
    public function delete(Customer $customer, CartItem $cartItem): Response
    {
        return $cartItem->cart_id === $customer->cart()->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
