@php
    use App\Support\ActorDisplay;
    use App\Support\Shop\ConversationKindLabel;

    $filterHref = fn (string $value) => route('shop.messages.index', ['filter' => $value, 'status' => $statusValue]);
    $statusHref = fn (string $value) => route('shop.messages.index', ['filter' => $filter, 'status' => $value]);
@endphp
<x-layouts.shop title="Messages — Art Store">
    <div class="flex flex-wrap items-end gap-6">
        <div>
            <h1 class="font-display text-4xl leading-tight text-ink">Messages</h1>
            <p class="mt-2 text-lg text-ink-muted">Your conversations with makers and with us.</p>
        </div>
        <x-ui.button variant="secondary" :href="route('shop.support')" class="ml-auto">Contact support</x-ui.button>
    </div>

    <nav aria-label="Filter messages" class="mt-7 flex flex-wrap gap-2">
        <a href="{{ $filterHref('all') }}" @if ($filter === 'all') aria-current="true" @endif
           class="inline-flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-sm font-medium {{ $filter === 'all' ? 'border-ink bg-ink text-surface' : 'border-line-strong bg-surface text-ink-muted hover:border-accent hover:text-accent' }}">All</a>
        <a href="{{ $filterHref('unread') }}" @if ($filter === 'unread') aria-current="true" @endif
           class="inline-flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-sm font-medium {{ $filter === 'unread' ? 'border-ink bg-ink text-surface' : 'border-line-strong bg-surface text-ink-muted hover:border-accent hover:text-accent' }}">Unread</a>

        <span aria-hidden="true" class="mx-1 w-px self-stretch bg-line"></span>

        <a href="{{ $statusHref('open') }}" @if ($statusValue === 'open') aria-current="true" @endif
           class="inline-flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-sm font-medium {{ $statusValue === 'open' ? 'border-ink bg-ink text-surface' : 'border-line-strong bg-surface text-ink-muted hover:border-accent hover:text-accent' }}">Open</a>
        <a href="{{ $statusHref('resolved') }}" @if ($statusValue === 'resolved') aria-current="true" @endif
           class="inline-flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-sm font-medium {{ $statusValue === 'resolved' ? 'border-ink bg-ink text-surface' : 'border-line-strong bg-surface text-ink-muted hover:border-accent hover:text-accent' }}">Resolved</a>
        <a href="{{ $statusHref('all') }}" @if ($statusValue === 'all') aria-current="true" @endif
           class="inline-flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-sm font-medium {{ $statusValue === 'all' ? 'border-ink bg-ink text-surface' : 'border-line-strong bg-surface text-ink-muted hover:border-accent hover:text-accent' }}">All statuses</a>
    </nav>

    @if ($conversations->isEmpty())
        <p class="mt-10 text-lg text-ink-muted">Nothing yet.</p>
    @else
        <ul class="mt-6 max-w-3xl divide-y divide-line border-y border-line">
            @foreach ($conversations as $conversation)
                @php
                    $isDesk = $conversation->kind->isDesk();
                    $counterpartLabel = $isDesk ? ActorDisplay::SUPPORT_DESK : ($conversation->seller?->name ?? $conversation->counterpartName($viewer));
                    $rowOrderId = $conversation->order_id ?? $conversation->fulfillment?->order_id;
                    $subtitle = $conversation->title;
                    $subtitle = match (true) {
                        $conversation->listing !== null => ($subtitle ? $subtitle.' · ' : '').$conversation->listing->title,
                        $rowOrderId !== null => ($subtitle ? $subtitle.' · ' : '').'Order '.$rowOrderId,
                        default => $subtitle,
                    };
                    $subtitle ??= $conversation->kind->topic($conversation->fulfillment?->order_id, null);
                    $isResolved = $conversation->resolved_at !== null;
                @endphp
                <li>
                    <a href="{{ route('shop.messages.show', $conversation) }}" class="flex gap-5 py-6 hover:text-accent">
                        <x-ui.avatar :name="$counterpartLabel" size="md" class="mt-0.5 shrink-0" />

                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-2.5">
                                <span class="text-lg font-semibold text-ink">{{ $counterpartLabel }}</span>
                                <span class="inline-flex items-center rounded-full bg-accent-soft px-2 py-0.5 text-xs font-semibold tracking-wide text-accent-strong">{{ ConversationKindLabel::of($conversation->kind) }}</span>
                                @if ($conversation->unread_count > 0)
                                    <span class="inline-flex items-center rounded-full bg-accent px-2 py-0.5 text-xs font-semibold text-on-accent">{{ $conversation->unread_count }} unread</span>
                                @elseif ($isResolved)
                                    <span class="inline-flex items-center gap-1 text-sm text-success">
                                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" width="14" height="14" aria-hidden="true"><path d="m5 10 3.5 3.5L15 7" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                        Resolved
                                    </span>
                                @endif
                                <span class="ml-auto shrink-0 text-sm text-ink-faint">{{ $conversation->last_message_at?->isToday() ? 'Today' : $conversation->last_message_at?->format('M j') }}</span>
                            </p>
                            <p class="mt-1 truncate text-ink-muted">{{ $subtitle }}</p>
                            @if ($conversation->latestMessage)
                                <p class="mt-1 truncate text-ink-faint">{{ str($conversation->latestMessage->body)->limit(120) }}</p>
                            @endif
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="mt-8 max-w-3xl">{{ $conversations->links() }}</div>
    @endif
</x-layouts.shop>
