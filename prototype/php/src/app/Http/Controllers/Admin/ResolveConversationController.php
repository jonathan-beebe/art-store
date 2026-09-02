<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Messaging\ResolveConversation;
use App\Models\Conversation;
use Illuminate\Http\RedirectResponse;

final class ResolveConversationController extends AdminController
{
    public function __invoke(Conversation $conversation, ResolveConversation $resolve): RedirectResponse
    {
        $this->authorize('resolve', $conversation);

        $resolve($conversation, $this->admin(), $this->now());

        return redirect()->route('admin.messages.show', $conversation);
    }
}
