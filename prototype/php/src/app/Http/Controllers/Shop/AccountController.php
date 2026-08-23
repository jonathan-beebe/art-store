<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Models\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class AccountController extends ShopController
{
    public function show(): View
    {
        $visitor = $this->visitor();

        return view('shop.account', [
            'customer' => $visitor,
            'notifications' => $visitor->notifications()->orderByDesc('id')->get(),
        ]);
    }

    public function readNotification(Notification $notification): RedirectResponse
    {
        $this->authorizeVisitor('markRead', $notification);

        $notification->markRead($this->now());

        return redirect()->route('shop.account');
    }
}
