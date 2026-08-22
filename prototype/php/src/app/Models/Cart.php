<?php

namespace App\Models;

use App\Domain\Cart\CartLine;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['customer_id'])]
class Cart extends Model
{
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * @return list<CartLine>
     */
    public function lines(): array
    {
        return $this->items->map(fn (CartItem $item): CartLine => $item->toLine())->all();
    }
}
