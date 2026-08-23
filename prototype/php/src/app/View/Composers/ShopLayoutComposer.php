<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Support\CustomerIdentity;
use Illuminate\View\View;

/**
 * The two counts the storefront header carries on every page. Bound to the
 * shop layout, so a page renders them without its controller passing them.
 */
final readonly class ShopLayoutComposer
{
    public function compose(View $view): void
    {
        $visitor = CustomerIdentity::current();

        if ($visitor === null) {
            return;
        }

        $view->with([
            'cartItemCount' => (int) $visitor->currentCart()->items()->sum('quantity'),
            'unreadNotificationCount' => $visitor->unreadNotifications()->count(),
        ]);
    }
}
