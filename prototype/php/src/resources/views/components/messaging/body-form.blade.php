{{--
    The admin's message form, shared by the thread reply (no `class`
    override — sits bare in the pane, like the seller thread's composer)
    and the standalone "message this customer/seller" forms on the
    customer/seller show pages (`class` boxes it under their own heading).
    Admin-exclusive: nothing else on the site shares this component.
--}}
@props(['action', 'label' => 'Reply', 'submitLabel' => 'Send', 'class' => 'mt-6'])

<form method="POST" action="{{ $action }}" class="{{ $class }}">
    @csrf

    <label for="body" class="sr-only">{{ $label }}</label>
    <div class="overflow-hidden rounded-lg bg-white outline-1 -outline-offset-1 outline-stone-300 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-stone-600 dark:bg-white/5 dark:outline-white/10 dark:focus-within:outline-stone-500">
        <textarea
            id="body" name="body" required rows="4" maxlength="2000" placeholder="Write a message&hellip;"
            class="block w-full resize-none bg-transparent px-3 py-2 text-sm text-stone-900 placeholder:text-stone-400 focus:outline-none dark:text-white dark:placeholder:text-stone-500"
        >{{ old('body') }}</textarea>
    </div>
    @error('body')
        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror

    <div class="mt-2 flex justify-end">
        <button type="submit" class="rounded-md bg-stone-700 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-stone-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-700">{{ $submitLabel }}</button>
    </div>
</form>
