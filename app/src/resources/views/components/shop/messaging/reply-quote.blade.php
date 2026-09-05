{{--
    The composer's reply-quote strip: what `?reply_to=` on the thread route
    prefills. `reply_to_message_id` rides the POST as a hidden field, so the
    quote survives a validation or rate-limit round trip the same way the
    body does.
--}}
@props(['id', 'name', 'excerpt', 'cancelUrl'])

<div class="mb-2 flex items-start gap-3 rounded-field border-l-2 border-accent bg-surface px-4 py-2.5 text-sm text-ink-muted">
    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" width="14" height="14" class="mt-1 shrink-0">
        <path d="M8 5 4 9l4 4M4 9h8a4 4 0 0 1 4 4v2" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
    <p class="min-w-0">Replying to <strong class="font-semibold text-ink">{{ $name }}</strong> — <em class="not-italic">{{ $excerpt }}</em></p>
    <a href="{{ $cancelUrl }}" class="ml-auto shrink-0 font-medium text-ink-faint hover:text-accent">Cancel</a>
</div>
<input type="hidden" name="reply_to_message_id" value="{{ $id }}">
