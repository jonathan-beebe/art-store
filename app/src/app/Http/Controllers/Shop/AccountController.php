<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\DatabaseNotification;

final class AccountController extends ShopController
{
    private const NOTIFICATIONS_PER_PAGE = 20;

    public function show(): View
    {
        $visitor = $this->visitor();

        return view('shop.account', [
            'customer' => $visitor,
            'notifications' => $visitor->notifications()->paginate(self::NOTIFICATIONS_PER_PAGE),
        ]);
    }

    public function readNotification(DatabaseNotification $notification): RedirectResponse
    {
        $this->authorizeVisitor('markRead', $notification);

        $notification->markAsRead();

        return redirect()->route('shop.account');
    }
}
