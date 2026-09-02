<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Messaging\ResolveConversation;
use App\Models\Conversation;
use Illuminate\Http\RedirectResponse;

final class ResolveConversationController extends SellerController
{
    public function __invoke(Conversation $conversation, ResolveConversation $resolveConversation): RedirectResponse
    {
        $this->authorize('resolve', $conversation);

        $resolveConversation($conversation, $this->seller(), $this->now());

        return redirect()
            ->route('seller.messages.show', $conversation)
            ->with('status', 'Marked resolved.');
    }
}
