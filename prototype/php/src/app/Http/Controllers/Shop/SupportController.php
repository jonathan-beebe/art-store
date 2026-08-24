<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Messaging\OpenConversation;
use App\Domain\Messaging\ConversationSubject;
use App\Domain\RateLimiting\RateLimitName;
use App\Models\Admin;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Http\RedirectResponse;

final class SupportController extends ShopController
{
    public function __invoke(OpenConversation $openConversation, RateLimitGate $rateLimit): RedirectResponse
    {
        $rateLimit->check(RateLimitName::ConversationOpen, (string) $this->visitor()->id);

        $admin = Admin::platformAdmin();

        if ($admin === null) {
            return back()->withErrors(['support' => 'No admin is available right now.']);
        }

        $conversation = $openConversation(
            ConversationSubject::adminCustomer($admin->id, $this->visitor()->id),
            $this->now(),
        );

        return redirect()->route('shop.messages.show', $conversation);
    }
}
