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
use Illuminate\View\View;

/**
 * The unread-message count and the nav rail's per-section counts (DSGN-006)
 * — every number the admin site's nav carries on every page. Bound to the
 * admin layout, so a page renders them without its controller passing them
 * along. Each count is a bare `count()` — one row, no joins — which is what
 * "cheap" means here; a section with nothing that cheap to show (accounting,
 * ledger, payouts, stats, logs, the dashboard itself) carries no count at
 * all rather than one worth a real query.
 */
final readonly class AdminLayoutComposer
{
    public function compose(View $view): void
    {
        $admin = auth('admin')->user();

        if (! $admin instanceof Admin) {
            return;
        }

        $view->with('unreadMessageCount', Message::query()->unreadInInboxOf($admin)->count());

        $view->with('navCounts', [
            'admin.sellers.index' => Seller::query()->count(),
            'admin.customers.index' => Customer::query()->count(),
            'admin.listings.index' => Listing::query()->count(),
            'admin.orders.index' => Order::query()->count(),
            'admin.fulfillments.index' => Fulfillment::query()->count(),
        ]);
    }
}
