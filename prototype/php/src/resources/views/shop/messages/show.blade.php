@php
    use App\Domain\Auth\ActorType;
    use App\Domain\Messaging\ConversationStatus;
    use App\Domain\Messaging\MessageBody;
    use App\Support\ActorDisplay;
    use App\Support\Shop\ConversationKindLabel;
    use Illuminate\Support\Str;

    $isDesk = $conversation->kind->isDesk();
    $headerName = $isDesk ? ActorDisplay::SUPPORT_DESK : ($conversation->seller?->name ?? $conversation->counterpartName($viewer));
    $headerTitle = $conversation->title ?? $conversation->kind->topic($conversation->fulfillment?->order_id, null);
    $contextOrderId = $conversation->order_id ?? $conversation->fulfillment?->order_id;
    $isResolved = $conversation->status() === ConversationStatus::Resolved;
    $isMine = fn ($threadMessage) => $threadMessage->sender_type === ActorType::Customer->value && $threadMessage->sender_id === $conversation->customer_id;
    $nameOf = fn ($threadMessage) => $isMine($threadMessage) ? 'You' : $threadMessage->senderName();
    // The avatar's own initial reads the customer's real name (or "Me" with
    // none), never the transcript label "You" — an avatar initialled from
    // that literal word reads as a stranger's, not the customer's own.
    $avatarNameOf = fn ($threadMessage) => $isMine($threadMessage) ? ($conversation->customer?->name ?? 'Me') : $threadMessage->senderName();
    $lastDay = null;
@endphp
<x-layouts.shop :title="$headerTitle.' — Art Store'">
    <a href="{{ route('shop.messages.index') }}" class="inline-flex items-center gap-1.5 text-sm text-ink-muted hover:text-accent">
        <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16" aria-hidden="true">
            <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 0 1 0 1.06L9.06 10l3.73 3.71a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" />
        </svg>
        All messages
    </a>

    <div class="mt-4 flex max-w-3xl flex-wrap items-start gap-6">
        <div class="min-w-0 flex-1">
            <h1 class="font-display text-3xl leading-tight text-ink">{{ $headerTitle }}</h1>
            <p class="mt-2 flex flex-wrap items-center gap-2.5 text-lg text-ink-muted">
                <span>with <strong class="font-semibold text-ink">{{ $headerName }}</strong>@if (! $isDesk && $conversation->seller?->shop_name) · {{ $conversation->seller->shop_name }} @endif</span>
                <span class="inline-flex items-center rounded-full bg-accent-soft px-2 py-0.5 text-xs font-semibold tracking-wide text-accent-strong">{{ ConversationKindLabel::of($conversation->kind) }}</span>
            </p>
        </div>

        @if ($conversation->listing)
            <a href="{{ route('shop.listing', $conversation->listing) }}" class="flex shrink-0 items-center gap-3 rounded-field border border-line bg-surface p-2 pr-4 hover:border-accent">
                <img src="{{ $conversation->listing->imageUrl() }}" alt="" class="size-14 rounded-field object-cover">
                <span class="flex flex-col">
                    <span class="text-xs text-ink-faint">About</span>
                    <span class="font-semibold leading-tight text-ink">{{ $conversation->listing->title }}</span>
                    <span class="text-sm text-ink-muted">{{ $conversation->listing->price()->format() }}</span>
                </span>
            </a>
        @elseif ($contextOrderId)
            <a href="{{ route('shop.order', $contextOrderId) }}" class="shrink-0 font-semibold text-accent hover:underline">Order {{ $contextOrderId }} →</a>
        @endif
    </div>

    <ol class="mt-9 max-w-3xl list-none space-y-7">
        @foreach ($conversation->messages as $threadMessage)
            @php
                $day = $threadMessage->sent_at->toDateString();
                $mine = $isMine($threadMessage);
            @endphp

            @if ($day !== $lastDay)
                @php $lastDay = $day; @endphp
                <li class="flex items-center gap-4 text-sm text-ink-faint before:h-px before:flex-1 before:bg-line after:h-px after:flex-1 after:bg-line">
                    {{ $threadMessage->sent_at->isToday() ? 'Today' : $threadMessage->sent_at->format('F j') }}
                </li>
            @endif

            <li id="msg_{{ $threadMessage->id }}" class="flex max-w-[90%] items-start gap-4 sm:max-w-[78%] {{ $mine ? 'ml-auto flex-row-reverse' : '' }}">
                <x-ui.avatar :name="$avatarNameOf($threadMessage)" size="md" class="shrink-0" />
                <div class="min-w-0 {{ $mine ? 'rounded-2xl rounded-tr-sm border border-line bg-accent-soft px-4 py-2.5' : '' }}">
                    <p class="flex flex-wrap items-baseline gap-2.5">
                        <span class="font-semibold text-ink">{{ $nameOf($threadMessage) }}</span>
                        <span class="text-sm text-ink-faint">{{ $threadMessage->sent_at->format('g:ia') }}</span>
                        <a href="{{ route('shop.messages.show', [$conversation, 'reply_to' => $threadMessage->id]) }}" class="ml-auto text-sm text-ink-faint hover:text-accent">Reply</a>
                    </p>

                    @if ($threadMessage->replyTo)
                        <p class="mt-1.5 flex gap-2 rounded-r-field border-l-2 border-line-strong bg-canvas px-3.5 py-2 text-sm text-ink-muted">
                            <a href="#msg_{{ $threadMessage->replyTo->id }}" class="min-w-0 hover:text-accent">
                                <strong class="font-semibold text-ink">{{ $isMine($threadMessage->replyTo) ? 'You' : $threadMessage->replyTo->senderName() }}</strong>
                                — {{ Str::limit($threadMessage->replyTo->body, 80) }}
                            </a>
                        </p>
                    @endif

                    <p class="mt-1.5 whitespace-pre-line text-lg leading-relaxed text-ink">{{ $threadMessage->body }}</p>
                </div>
            </li>
        @endforeach
    </ol>

    @if ($isResolved)
        <div role="status" class="mt-8 flex max-w-3xl items-center gap-3 rounded-card border border-success-line bg-success-surface px-5 py-3.5 text-success">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" width="20" height="20" class="shrink-0" aria-hidden="true">
                <path d="m5 10 3.5 3.5L15 7" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <p>
                <strong class="font-semibold">{{ $conversation->resolvedBy ? ActorDisplay::nameOf($conversation->resolvedBy) : 'This thread' }} marked this resolved</strong>
                {{ $conversation->resolved_at?->format('M j \a\t g:ia') }}. Reply below if there's anything else — that reopens it.
            </p>
        </div>
    @elseif (session('reopened'))
        <div role="status" class="mt-8 flex max-w-3xl items-center gap-3 rounded-card border border-success-line bg-success-surface px-5 py-3.5 text-success">
            <p>Your reply reopened this conversation.</p>
        </div>
    @endif

    @visitorCan('post', $conversation)
        <form method="POST" action="{{ route('shop.messages.store', $conversation) }}" class="mt-8 max-w-3xl">
            @csrf

            @if ($replyTo)
                <x-shop.messaging.reply-quote
                    :id="$replyTo->id"
                    :name="$isMine($replyTo) ? 'You' : $replyTo->senderName()"
                    :excerpt="Str::limit($replyTo->body, 80)"
                    :cancel-url="route('shop.messages.show', $conversation)"
                />
            @endif

            @php
                // "Art Store Support"'s first word alone read as "Write to
                // Art…" — the desk's own two words, "Art Store", stand for
                // it in the composer the way a maker's first name does.
                $composerTarget = $isDesk ? 'Art Store' : Str::before($headerName, ' ');
            @endphp
            <label for="body" class="sr-only">Reply</label>
            <x-shop.messaging.composer
                name="body"
                :value="old('body', '')"
                :placeholder="'Write to '.$composerTarget.'…'"
                :maxlength="MessageBody::MAX_LENGTH"
            >
                <x-ui.button variant="primary">Send</x-ui.button>
            </x-shop.messaging.composer>
            @error('body')
                <p class="mt-2 text-danger">{{ $message }}</p>
            @enderror
        </form>
    @endvisitorCan
</x-layouts.shop>
