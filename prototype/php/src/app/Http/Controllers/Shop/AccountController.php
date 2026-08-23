<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\DatabaseNotification;

final class AccountController extends ShopController
{
    public function show(): View
    {
        $visitor = $this->visitor();

        return view('shop.account', [
            'customer' => $visitor,
            'notifications' => $visitor->notifications()->get(),
        ]);
    }

    public function readNotification(DatabaseNotification $notification): RedirectResponse
    {
        $this->authorizeVisitor('markRead', $notification);

        $notification->markAsRead();

        return redirect()->route('shop.account');
    }
}
