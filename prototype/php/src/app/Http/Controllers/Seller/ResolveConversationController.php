<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Messaging\ResolveConversation;
use App\Http\Requests\Seller\MessagesQueryRequest;
use App\Models\Conversation;
use Illuminate\Http\RedirectResponse;

final class ResolveConversationController extends SellerController
{
    public function __invoke(Conversation $conversation, MessagesQueryRequest $request, ResolveConversation $resolveConversation): RedirectResponse
    {
        $this->authorize('resolve', $conversation);

        $resolveConversation($conversation, $this->seller(), $this->now());

        // The form's own action URL carries the pane's current selection
        // onward, so resolving a thread doesn't snap its pane back to the
        // index route's defaults.
        return redirect()
            ->route('seller.messages.show', ['conversation' => $conversation, ...$request->paneQuery()->toRouteParams()])
            ->with('status', 'Marked resolved.');
    }
}
