<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Messaging\MarkConversationRead;
use App\Actions\Messaging\PostMessage;
use App\Domain\Auth\ActorType;
use App\Http\Requests\Admin\PostMessageRequest;
use App\Models\Conversation;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class MessageController extends AdminController
{
    public function index(): View
    {
        $admin = $this->admin();

        $conversations = Conversation::query()
            ->withParticipant($admin)
            ->with(['seller', 'customer', 'admin', 'listing', 'fulfillment', 'latestMessage'])
            ->withUnreadCountFor($admin)
            ->orderByDesc('last_message_at')
            ->get();

        return view('admin.messages.index', ['conversations' => $conversations, 'viewer' => ActorType::Admin]);
    }

    public function show(Conversation $conversation, MarkConversationRead $markRead): View
    {
        $this->authorize('view', $conversation);

        $markRead($conversation, $this->admin(), $this->now());

        $conversation->load([
            'seller', 'customer', 'admin', 'listing', 'fulfillment',
            'messages' => fn (Relation $query): Relation => $query->orderBy('id'),
            'messages.sender',
        ]);

        return view('admin.messages.show', [
            'conversation' => $conversation,
            'viewer' => ActorType::Admin,
        ]);
    }

    public function store(PostMessageRequest $request, Conversation $conversation, PostMessage $postMessage): RedirectResponse
    {
        $postMessage($conversation, $this->admin(), $request->body(), $this->now());

        return redirect()->route('admin.messages.show', $conversation);
    }
}
