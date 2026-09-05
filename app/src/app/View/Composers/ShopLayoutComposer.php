<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Models\Message;
use App\Support\CustomerIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The three counts the storefront header carries on every page. Bound to the
 * shop layout, so a page renders them without its controller passing them.
 *
 * All three read as scalar subqueries of one row — the cart sum through
 * `cartItems()` (so a visitor who has not created a cart costs no lookup
 * or find-or-create), and the two unread counts beside it — one round
 * trip to the database for all three.
 */
final readonly class ShopLayoutComposer
{
    public function compose(View $view): void
    {
        $visitor = CustomerIdentity::current();

        if ($visitor === null) {
            return;
        }

        /**
         * @var object{
         *     cart_items: int|string|null,
         *     unread_notifications: int|string,
         *     unread_messages: int|string,
         * } $counts
         */
        $counts = DB::query()
            ->selectSub($visitor->cartItems()->getQuery()->selectRaw('coalesce(sum(quantity), 0)'), 'cart_items')
            ->selectSub($visitor->unreadNotifications()->getQuery()->reorder()->selectRaw('count(*)'), 'unread_notifications')
            ->selectSub(Message::query()->unreadInInboxOf($visitor)->selectRaw('count(*)'), 'unread_messages')
            ->sole();

        $view->with([
            'cartItemCount' => (int) $counts->cart_items,
            'unreadNotificationCount' => (int) $counts->unread_notifications,
            'unreadMessageCount' => (int) $counts->unread_messages,
        ]);
    }
}
