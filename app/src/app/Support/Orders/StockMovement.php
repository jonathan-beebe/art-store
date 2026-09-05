<?php

declare(strict_types=1);

namespace App\Support\Orders;

use App\Models\CartItem;
use App\Models\OrderItem;
use App\Models\Unit;
use App\Models\Variant;
use LogicException;

/**
 * Where a line's stock movement lives, at placement and at every reversal —
 * a legacy line still sells and restocks the listing it always has;
 * `docs/item-configurator.md` §2.4 extends the same two moments to a
 * configured line's variant or, for a serialized line, its claimed unit.
 * Shared by {@see \App\Actions\Orders\PlaceOrder} (claim), and
 * {@see \App\Actions\Orders\CancelOrder}, {@see \App\Actions\Fulfillment\DeclineFulfillment},
 * and {@see \App\Actions\Orders\FinalizeOrder} (claim on retry, release on
 * decline/cancel) so the branch lives in one place.
 *
 * The caller is responsible for eager-loading `listing`/`variant`/`unit`
 * locked for update before calling either method — this class only applies
 * the movement to whatever row is already in hand.
 */
final class StockMovement
{
    private function __construct() {} // @codeCoverageIgnore

    public static function claim(CartItem|OrderItem $item): void
    {
        if (! $item->hasVariant()) {
            $item->listing->sell($item->quantity);

            return;
        }

        if ($item->unit_id !== null) {
            self::unit($item)->sell();

            return;
        }

        self::variant($item)->decrementQuantity($item->quantity);
    }

    public static function release(CartItem|OrderItem $item): void
    {
        if (! $item->hasVariant()) {
            $item->listing->restock($item->quantity);

            return;
        }

        if ($item->unit_id !== null) {
            self::unit($item)->restock();

            return;
        }

        self::variant($item)->restoreQuantity($item->quantity);
    }

    private static function variant(CartItem|OrderItem $item): Variant
    {
        return $item->variant ?? throw new LogicException('A configured line always resolves to a variant.');
    }

    private static function unit(CartItem|OrderItem $item): Unit
    {
        return $item->unit ?? throw new LogicException('A line naming a unit id always resolves to one.');
    }
}
