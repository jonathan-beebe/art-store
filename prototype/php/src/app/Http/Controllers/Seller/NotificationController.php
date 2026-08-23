<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class NotificationController extends SellerController
{
    public function index(): View
    {
        return view('seller.notifications', [
            'notifications' => $this->seller()->notifications()->latest('id')->get(),
        ]);
    }

    public function markRead(Notification $notification): RedirectResponse
    {
        $this->authorize('markRead', $notification);

        $notification->markRead($this->now());

        return redirect()->route('seller.notifications.index')->with('status', 'Marked as read.');
    }
}
