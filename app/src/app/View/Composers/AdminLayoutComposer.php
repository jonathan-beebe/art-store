<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Fulfillment;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Order;
use App\Models\Seller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The unread-message count and the nav rail's per-section counts (DSGN-006)
 * — every number the admin site's nav carries on every page. Bound to the
 * admin layout, so a page renders them without its controller passing them
 * along. Each count is a bare `count()` — one row, no joins — which is what
 * "cheap" means here; a section with nothing that cheap to show (accounting,
 * ledger, payouts, stats, logs, the dashboard itself) carries no count at
 * all — a meaningful count there would cost a real query.
 *
 * The six counts read as scalar subqueries of one row — a page renders
 * the same six numbers for one round trip to the database.
 */
final readonly class AdminLayoutComposer
{
    public function compose(View $view): void
    {
        $admin = auth('admin')->user();

        if (! $admin instanceof Admin) {
            return;
        }

        /**
         * @var object{
         *     unread_messages: int|string,
         *     sellers: int|string,
         *     customers: int|string,
         *     listings: int|string,
         *     orders: int|string,
         *     fulfillments: int|string,
         * } $counts
         */
        $counts = DB::query()
            ->selectSub(Message::query()->unreadInInboxOf($admin)->selectRaw('count(*)'), 'unread_messages')
            ->selectSub(Seller::query()->selectRaw('count(*)'), 'sellers')
            ->selectSub(Customer::query()->selectRaw('count(*)'), 'customers')
            ->selectSub(Listing::query()->selectRaw('count(*)'), 'listings')
            ->selectSub(Order::query()->selectRaw('count(*)'), 'orders')
            ->selectSub(Fulfillment::query()->selectRaw('count(*)'), 'fulfillments')
            ->sole();

        $view->with('unreadMessageCount', (int) $counts->unread_messages);

        $view->with('navCounts', [
            'admin.sellers.index' => (int) $counts->sellers,
            'admin.customers.index' => (int) $counts->customers,
            'admin.listings.index' => (int) $counts->listings,
            'admin.orders.index' => (int) $counts->orders,
            'admin.fulfillments.index' => (int) $counts->fulfillments,
        ]);
    }
}
