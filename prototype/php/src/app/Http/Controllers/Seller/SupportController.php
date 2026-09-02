<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Messaging\OpenConversation;
use App\Domain\Messaging\ThreadOpening;
use App\Domain\Messaging\ThreadTitle;
use App\Domain\RateLimiting\RateLimitName;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Http\RedirectResponse;

/**
 * Opens a fresh, empty admin/seller thread and lands the seller on it to
 * type the first message — a placeholder title stands in for the real
 * support-thread form (FEAT-041), which types one.
 */
final class SupportController extends SellerController
{
    private const PLACEHOLDER_TITLE = 'Support';

    public function __invoke(OpenConversation $openConversation, RateLimitGate $rateLimit): RedirectResponse
    {
        $rateLimit->check(RateLimitName::ConversationOpen, (string) $this->seller()->id);

        $conversation = $openConversation(
            ThreadOpening::adminSeller($this->seller()->id, ThreadTitle::of(self::PLACEHOLDER_TITLE)),
            $this->now(),
        );

        return redirect()->route('seller.messages.show', $conversation);
    }
}
