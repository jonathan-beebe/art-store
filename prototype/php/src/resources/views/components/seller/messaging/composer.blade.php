{{--
    The reply box every seller thread ends with. `body` is the one field the
    backend reads (`PostMessageRequest`), so the name/required/maxlength here
    stay in lockstep with its `max:` rule — over-length and rate-limited
    submissions both come back through `old('body')` into this same textarea.
--}}
@props(['action', 'submitLabel' => 'Send'])

<form method="POST" action="{{ $action }}" class="mt-6">
    @csrf

    <label for="body" class="sr-only">Reply</label>
    <div class="overflow-hidden rounded-lg bg-white outline-1 -outline-offset-1 outline-gray-300 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-indigo-600 dark:bg-white/5 dark:outline-white/10 dark:focus-within:outline-indigo-500">
        <textarea
            id="body" name="body" required rows="3" maxlength="2000" placeholder="Write a reply&hellip;"
            class="block w-full resize-none bg-transparent px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none dark:text-white dark:placeholder:text-gray-500"
        >{{ old('body') }}</textarea>
    </div>
    @error('body')
        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror

    <div class="mt-2 flex justify-end">
        <button type="submit" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">{{ $submitLabel }}</button>
    </div>
</form>
