<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Messaging\ReopenConversation;
use App\Http\Requests\Seller\MessagesQueryRequest;
use App\Models\Conversation;
use Illuminate\Http\RedirectResponse;

final class ReopenConversationController extends SellerController
{
    public function __invoke(Conversation $conversation, MessagesQueryRequest $request, ReopenConversation $reopenConversation): RedirectResponse
    {
        $this->authorize('reopen', $conversation);

        $reopenConversation($conversation, $this->seller());

        // The form's own action URL carries the pane's `filter`/`status`
        // onward, so reopening a thread doesn't snap its pane back to the
        // index route's defaults.
        return redirect()
            ->route('seller.messages.show', ['conversation' => $conversation, 'filter' => $request->filter(), 'status' => $request->status()])
            ->with('status', 'Reopened.');
    }
}
