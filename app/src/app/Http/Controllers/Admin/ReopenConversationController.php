<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Messaging\ReopenConversation;
use App\Http\Requests\Admin\MessagesQueryRequest;
use App\Models\Conversation;
use Illuminate\Http\RedirectResponse;

final class ReopenConversationController extends AdminController
{
    public function __invoke(Conversation $conversation, MessagesQueryRequest $request, ReopenConversation $reopen): RedirectResponse
    {
        $this->authorize('reopen', $conversation);

        $reopen($conversation, $this->admin());

        // The form's own action URL carries the pane's current domain
        // onward, so reopening a thread doesn't snap its pane back to the
        // desk's unscoped default.
        return redirect()->route('admin.messages.show', [
            'conversation' => $conversation,
            'domain' => $request->domain(),
        ]);
    }
}
