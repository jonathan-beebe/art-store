<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class NotificationController extends Controller
{
    public function index(): View
    {
        return view('seller.notifications', [
            'notifications' => auth('seller')->user()->notifications()->latest('id')->get(),
        ]);
    }

    public function markRead(string $notification): RedirectResponse
    {
        $notification = auth('seller')->user()->notifications()->findOrFail($notification);

        $notification->update(['read_at' => now()]);

        return redirect()->route('seller.notifications.index')->with('status', 'Marked as read.');
    }
}
