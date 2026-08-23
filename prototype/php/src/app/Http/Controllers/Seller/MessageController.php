<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Messaging\MarkConversationRead;
use App\Actions\Messaging\PostMessage;
use App\Domain\Auth\ActorType;
use App\Http\Requests\Seller\PostMessageRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class MessageController extends SellerController
{
    public function index(): View
    {
        $seller = $this->seller();

        $conversations = Conversation::query()
            ->withParticipant($seller)
            ->with(['seller', 'customer', 'admin', 'listing', 'fulfillment', 'latestMessage'])
            ->withCount(['messages as unread_count' => fn (Builder $query): Builder => $this->unreadByViewer($query, $seller)])
            ->orderByDesc('last_message_at')
            ->get();

        return view('seller.messages.index', ['conversations' => $conversations, 'viewer' => ActorType::Seller]);
    }

    public function show(Conversation $conversation, MarkConversationRead $markRead): View
    {
        $this->authorize('view', $conversation);

        $markRead($conversation, $this->seller(), $this->now());

        $conversation->load([
            'seller', 'customer', 'admin', 'listing', 'fulfillment',
            'messages' => fn (Relation $query): Relation => $query->orderBy('id'),
            'messages.sender',
        ]);

        return view('seller.messages.show', [
            'conversation' => $conversation,
            'faqPrefill' => $conversation->faqPrefill(),
            'viewer' => ActorType::Seller,
        ]);
    }

    public function store(PostMessageRequest $request, Conversation $conversation, PostMessage $postMessage): RedirectResponse
    {
        $postMessage($conversation, $this->seller(), $request->body(), $this->now());

        return redirect()->route('seller.messages.show', $conversation);
    }

    /**
     * @param  Builder<Message>  $query
     * @return Builder<Message>
     */
    private function unreadByViewer(Builder $query, Seller $seller): Builder
    {
        return $query->unreadBy($seller);
    }
}
