<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Models\Conversation;
use App\Models\Message;
use App\Support\CustomerIdentity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

/**
 * The three counts the storefront header carries on every page. Bound to the
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
            'unreadMessageCount' => Message::query()
                ->unreadBy($visitor)
                ->whereHas('conversation', function (Builder $query) use ($visitor): Builder {
                    /** @var Builder<Conversation> $query */
                    return $query->withParticipant($visitor);
                })
                ->count(),
        ]);
    }
}
