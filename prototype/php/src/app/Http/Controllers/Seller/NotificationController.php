<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

final class NotificationController extends SellerController
{
    public function index(): View
    {
        return view('seller.notifications', [
            'notifications' => $this->seller()->notifications()->get(),
        ]);
    }

    public function markRead(DatabaseNotification $notification): RedirectResponse
    {
        $this->authorize('markRead', $notification);

        $notification->markAsRead();

        return redirect()->route('seller.notifications.index')->with('status', 'Marked as read.');
    }
}
