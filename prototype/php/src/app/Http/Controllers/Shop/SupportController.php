<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Messaging\OpenConversation;
use App\Domain\Messaging\ThreadOpening;
use App\Domain\Messaging\ThreadTitle;
use App\Domain\RateLimiting\RateLimitName;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Http\RedirectResponse;

/**
 * Opens a fresh, empty admin/customer thread and lands the visitor on it to
 * type the first message — a placeholder title stands in for the real
 * support-thread form (FEAT-043), which types one. `auth.customer` guards
 * this route, so the visitor is always a verified customer here.
 */
final class SupportController extends ShopController
{
    private const PLACEHOLDER_TITLE = 'Support';

    public function __invoke(OpenConversation $openConversation, RateLimitGate $rateLimit): RedirectResponse
    {
        $rateLimit->check(RateLimitName::ConversationOpen, (string) $this->visitor()->id);

        $conversation = $openConversation(
            ThreadOpening::adminCustomer($this->visitor()->id, ThreadTitle::of(self::PLACEHOLDER_TITLE)),
            $this->now(),
        );

        return redirect()->route('shop.messages.show', $conversation);
    }
}
