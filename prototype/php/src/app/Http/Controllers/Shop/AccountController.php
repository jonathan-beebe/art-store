<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Domain\Notifications\RecipientType;
use App\Models\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class AccountController extends ShopController
{
    public function show(): View
    {
        $visitor = $this->visitor();

        return $this->page('shop.account', [
            'customer' => $visitor,
            'notifications' => Notification::query()
                ->for(RecipientType::Customer, $visitor->id)
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function readNotification(Notification $notification): RedirectResponse
    {
        $this->authorizeVisitor('markRead', $notification);

        $notification->update(['read_at' => now()]);

        return redirect()->route('shop.account');
    }
}
