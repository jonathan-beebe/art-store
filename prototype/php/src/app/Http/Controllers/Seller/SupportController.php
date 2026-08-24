<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Messaging\OpenConversation;
use App\Domain\Messaging\ConversationSubject;
use App\Domain\RateLimiting\RateLimitName;
use App\Models\Admin;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Http\RedirectResponse;

final class SupportController extends SellerController
{
    public function __invoke(OpenConversation $openConversation, RateLimitGate $rateLimit): RedirectResponse
    {
        $rateLimit->check(RateLimitName::ConversationOpen, (string) $this->seller()->id);

        $admin = Admin::platformAdmin();

        if ($admin === null) {
            return back()->withErrors(['support' => 'No admin is available right now.']);
        }

        $conversation = $openConversation(
            ConversationSubject::adminSeller($admin->id, $this->seller()->id),
            $this->now(),
        );

        return redirect()->route('seller.messages.show', $conversation);
    }
}
