<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\Customer;

final class CurrentCart
{
    /**
     * A merge hands the verified customer whatever cart the anonymous visitor
     * was filling, so they can own two. The one holding items is the one the
     * visitor was shopping with.
     */
    public function __invoke(Customer $customer): Cart
    {
        return Cart::query()
            ->where('customer_id', $customer->id)
            ->withCount('items')
            ->orderByDesc('items_count')
            ->orderByDesc('id')
            ->first()
            ?? Cart::create(['customer_id' => $customer->id]);
    }
}
