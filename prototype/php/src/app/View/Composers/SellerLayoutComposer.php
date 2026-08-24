<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Models\Message;
use App\Models\Seller;
use Illuminate\View\View;

/**
 * The unread-message count the seller portal's nav carries on every page.
 * Bound to the seller layout, so a page renders it without its controller
 * passing it along.
 */
final readonly class SellerLayoutComposer
{
    public function compose(View $view): void
    {
        $seller = auth('seller')->user();

        if (! $seller instanceof Seller) {
            return;
        }

        $view->with('unreadMessageCount', Message::query()->unreadInInboxOf($seller)->count());
    }
}
