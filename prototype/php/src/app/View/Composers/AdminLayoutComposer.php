<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Models\Admin;
use App\Models\Message;
use Illuminate\View\View;

/**
 * The unread-message count the admin site's nav carries on every page.
 * Bound to the admin layout, so a page renders it without its controller
 * passing it along.
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
    }
}
