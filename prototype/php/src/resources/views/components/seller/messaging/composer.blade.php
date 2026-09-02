{{--
    The reply box every seller thread ends with. `body` is the one field the
    backend reads (`PostMessageRequest`), so the name/required/maxlength here
    stay in lockstep with its `max:` rule — over-length and rate-limited
    submissions both come back through `old('body')` into this same
    textarea, and `old('reply_to_message_id')` survives the same round trip.
    `public/composer.js` reads the `data-composer`/`data-composer-count`
    contract for the live counter and Cmd/Ctrl+Enter; the form still posts
    with it absent, since the counter is server-rendered first and growth is
    the `field-sizing-content` utility rather than script.
--}}
@props(['action', 'submitLabel' => 'Send', 'replyTo' => null, 'cancelUrl' => null])

@php
    $bodyOld = old('body', '');
    $replyToOld = old('reply_to_message_id', $replyTo?->id);
@endphp

<form method="POST" action="{{ $action }}" class="mt-6">
    @csrf
    <input type="hidden" name="reply_to_message_id" value="{{ $replyToOld }}">

    <label for="body" class="sr-only">Reply</label>
    <div class="overflow-hidden rounded-lg bg-white outline-1 -outline-offset-1 outline-gray-300 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-indigo-600 dark:bg-white/5 dark:outline-white/10 dark:focus-within:outline-indigo-500">
        @if ($replyTo)
            <div class="flex items-center gap-2 border-b border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" class="size-3.5 shrink-0"><path d="M8 5 4 9l4 4M4 9h8a4 4 0 0 1 4 4v2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                <span class="min-w-0 truncate">Replying to <strong class="font-semibold text-gray-900 dark:text-white">{{ $replyTo->senderName() }}</strong> &middot; <em class="text-gray-500 not-italic dark:text-gray-500">{{ str($replyTo->body)->limit(60) }}</em></span>
                <a href="{{ $cancelUrl }}" class="ml-auto shrink-0 rounded text-gray-500 hover:text-gray-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:text-gray-400 dark:hover:text-gray-200">Cancel</a>
            </div>
        @endif

        <textarea
            id="body" name="body" required rows="3"
            maxlength="{{ \App\Domain\Messaging\MessageBody::MAX_LENGTH }}"
            placeholder="Write a reply&hellip;"
            data-composer
            class="field-sizing-content block max-h-60 w-full resize-none bg-transparent px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none dark:text-white dark:placeholder:text-gray-500"
        >{{ $bodyOld }}</textarea>

        <div class="flex items-center gap-3 border-t border-gray-100 px-3 py-2 dark:border-white/10">
            <span data-composer-count class="text-xs text-gray-500 dark:text-gray-400">{{ number_format(mb_strlen($bodyOld)) }} / {{ number_format(\App\Domain\Messaging\MessageBody::MAX_LENGTH) }}</span>
            <span class="ml-auto text-xs text-gray-500 dark:text-gray-400"><kbd class="rounded border border-gray-300 px-1 font-mono dark:border-white/20">&#8984;</kbd> <kbd class="rounded border border-gray-300 px-1 font-mono dark:border-white/20">Enter</kbd> to send</span>
            <button type="submit" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">{{ $submitLabel }}</button>
        </div>
    </div>
    @error('body')
        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</form>
