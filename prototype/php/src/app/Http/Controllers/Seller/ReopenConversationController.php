<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Messaging\ReopenConversation;
use App\Models\Conversation;
use Illuminate\Http\RedirectResponse;

final class ReopenConversationController extends SellerController
{
    public function __invoke(Conversation $conversation, ReopenConversation $reopenConversation): RedirectResponse
    {
        $this->authorize('reopen', $conversation);

        $reopenConversation($conversation, $this->seller());

        return redirect()
            ->route('seller.messages.show', $conversation)
            ->with('status', 'Reopened.');
    }
}
