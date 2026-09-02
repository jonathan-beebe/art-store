{{--
    The seller and customer detail pages' "Message seller" / "Message
    customer" form — a fresh, titled thread through `OpenThread`, with an
    optional order for context. `contextOptions` is a `[id => label]` map
    (a seller's own fulfillments, or a customer's own orders); an empty map
    renders no select at all, since there is nothing yet to choose from.
    Admin-exclusive.
--}}
@props(['action', 'contextField', 'contextLabel', 'contextOptions' => [], 'selectedContextId' => null])

<form method="POST" action="{{ $action }}" class="mt-2 space-y-3">
    @csrf

    <div>
        <label for="title" class="block text-sm font-medium text-stone-900 dark:text-stone-100">Subject</label>
        <input
            id="title" name="title" type="text" required maxlength="{{ \App\Domain\Messaging\ThreadTitle::MAX_LENGTH }}" value="{{ old('title') }}"
            class="mt-1 block w-full rounded-md bg-white px-3 py-1.5 text-sm text-stone-900 outline outline-1 -outline-offset-1 outline-stone-300 focus:outline-2 focus:-outline-offset-2 focus:outline-stone-600 dark:bg-white/5 dark:text-stone-100 dark:outline-stone-600"
        >
        @error('title')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    @if (count($contextOptions) > 0)
        <div>
            <label for="{{ $contextField }}" class="block text-sm font-medium text-stone-900 dark:text-stone-100">{{ $contextLabel }}</label>
            <select
                id="{{ $contextField }}" name="{{ $contextField }}"
                class="mt-1 block w-full rounded-md bg-white px-3 py-1.5 text-sm text-stone-900 outline outline-1 -outline-offset-1 outline-stone-300 focus:outline-2 focus:-outline-offset-2 focus:outline-stone-600 dark:bg-white/5 dark:text-stone-100 dark:outline-stone-600"
            >
                <option value="">No order</option>
                @foreach ($contextOptions as $id => $label)
                    <option value="{{ $id }}" @selected((string) old($contextField, (string) $selectedContextId) === (string) $id)>{{ $label }}</option>
                @endforeach
            </select>
            @error($contextField)
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
    @endif

    <div>
        <label for="body" class="sr-only">Message</label>
        <div class="overflow-hidden rounded-lg bg-white outline-1 -outline-offset-1 outline-stone-300 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-stone-600 dark:bg-white/5 dark:outline-white/10 dark:focus-within:outline-stone-500">
            <textarea
                id="body" name="body" required rows="4" data-composer maxlength="{{ \App\Domain\Messaging\MessageBody::MAX_LENGTH }}"
                placeholder="Write a message&hellip;"
                class="block max-h-72 min-h-[6rem] w-full resize-none [field-sizing:content] bg-transparent px-3 py-2 text-sm text-stone-900 placeholder:text-stone-400 focus:outline-none dark:text-white dark:placeholder:text-stone-500"
            >{{ old('body') }}</textarea>
            <div class="flex items-center gap-3 border-t border-stone-100 px-3 py-1.5 dark:border-white/5">
                <span data-composer-count class="text-xs text-stone-500 dark:text-stone-500">{{ number_format(mb_strlen((string) old('body'))) }} / {{ number_format(\App\Domain\Messaging\MessageBody::MAX_LENGTH) }}</span>
            </div>
        </div>
        @error('body')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <button type="submit" class="rounded-md bg-stone-700 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-stone-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-700">Send</button>
</form>
