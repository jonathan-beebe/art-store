<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Messaging\ResolveConversation;
use App\Http\Requests\Admin\MessagesQueryRequest;
use App\Models\Conversation;
use Illuminate\Http\RedirectResponse;

final class ResolveConversationController extends AdminController
{
    public function __invoke(Conversation $conversation, MessagesQueryRequest $request, ResolveConversation $resolve): RedirectResponse
    {
        $this->authorize('resolve', $conversation);

        $resolve($conversation, $this->admin(), $this->now());

        // The form's own action URL carries the pane's current domain
        // onward, so resolving a thread doesn't snap its pane back to the
        // desk's unscoped default.
        return redirect()->route('admin.messages.show', [
            'conversation' => $conversation,
            'domain' => $request->domain(),
        ]);
    }
}
