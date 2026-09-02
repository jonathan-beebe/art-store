{{--
    The thread's reply composer — docs/messaging.md § "The composer" and §
    "Replying to a message". `replyTo` renders the quote block and rides the
    hidden field the "Reply" links point at; without it the form is a bare
    reply box. `public/composer.js` reads the textarea's `data-composer` and
    the count element's `data-composer-count`; the counter below is
    server-rendered first, so the form still works without the script.
    Admin-exclusive.
--}}
@props(['conversation', 'action', 'replyTo' => null, 'filter' => null, 'status' => null])

@php
    $cancelRouteParams = array_filter(
        ['conversation' => $conversation, 'filter' => $filter, 'status' => $status],
        fn ($value) => $value !== null,
    );
@endphp

<form method="POST" action="{{ $action }}" class="mt-6">
    @csrf

    @if ($replyTo)
        <div class="mb-2 flex items-start justify-between gap-3 rounded-md border border-stone-200 bg-stone-50 px-3 py-2 text-xs dark:border-white/10 dark:bg-white/5">
            <a href="#msg_{{ $replyTo->id }}" class="min-w-0 text-stone-600 dark:text-stone-400">
                <span class="font-semibold text-stone-800 dark:text-stone-200">Replying to {{ $replyTo->senderName() }}</span>
                <span class="block truncate">{{ str($replyTo->body)->limit(120) }}</span>
            </a>
            <a href="{{ route('admin.messages.show', $cancelRouteParams) }}" class="shrink-0 font-medium text-stone-600 underline dark:text-stone-400">Cancel</a>
        </div>
        <input type="hidden" name="reply_to_message_id" value="{{ $replyTo->id }}">
    @endif

    <label for="body" class="sr-only">Reply</label>
    <div class="overflow-hidden rounded-lg bg-white outline-1 -outline-offset-1 outline-stone-300 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-stone-600 dark:bg-white/5 dark:outline-white/10 dark:focus-within:outline-stone-500">
        <textarea
            id="body" name="body" required rows="3" data-composer maxlength="{{ \App\Domain\Messaging\MessageBody::MAX_LENGTH }}"
            placeholder="Write a reply&hellip;"
            class="block max-h-72 min-h-[4.5rem] w-full resize-none [field-sizing:content] bg-transparent px-3 py-2 text-sm text-stone-900 placeholder:text-stone-400 focus:outline-none dark:text-white dark:placeholder:text-stone-500"
        >{{ old('body') }}</textarea>
        <div class="flex items-center gap-3 border-t border-stone-100 px-3 py-1.5 dark:border-white/5">
            <span data-composer-count class="text-xs text-stone-500 dark:text-stone-500">{{ number_format(mb_strlen((string) old('body'))) }} / {{ number_format(\App\Domain\Messaging\MessageBody::MAX_LENGTH) }}</span>
            <span class="ml-auto hidden text-xs text-stone-500 dark:text-stone-500 sm:inline"><kbd data-composer-mod class="rounded border border-stone-300 px-1 dark:border-stone-600">Ctrl</kbd> + <kbd class="rounded border border-stone-300 px-1 dark:border-stone-600">Enter</kbd> to send</span>
            <button type="submit" class="rounded-md bg-stone-700 px-3 py-1.5 text-sm font-semibold text-white shadow-xs hover:bg-stone-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-700">Send</button>
        </div>
    </div>
    @error('body')
        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</form>
