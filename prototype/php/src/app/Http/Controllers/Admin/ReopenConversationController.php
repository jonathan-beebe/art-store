<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Messaging\ReopenConversation;
use App\Models\Conversation;
use Illuminate\Http\RedirectResponse;

final class ReopenConversationController extends AdminController
{
    public function __invoke(Conversation $conversation, ReopenConversation $reopen): RedirectResponse
    {
        $this->authorize('reopen', $conversation);

        $reopen($conversation, $this->admin());

        return redirect()->route('admin.messages.show', $conversation);
    }
}
