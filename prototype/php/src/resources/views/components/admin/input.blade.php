@props(['id', 'name', 'label', 'type' => 'text', 'value' => null, 'placeholder' => null])

{{-- The text/date-input twin of x-admin.select — same field idiom, same
     single source for the class list. --}}
<div>
    <label for="{{ $id }}" class="block text-sm/6 font-medium text-stone-900 dark:text-stone-100">{{ $label }}</label>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" @if ($placeholder) placeholder="{{ $placeholder }}" @endif value="{{ $value }}" class="mt-2 block w-full min-h-11 sm:min-h-0 rounded-md bg-white px-3 py-1.5 text-base text-stone-900 outline-1 -outline-offset-1 outline-stone-300 placeholder:text-stone-400 focus:outline-2 focus:-outline-offset-2 focus:outline-stone-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:placeholder:text-stone-500 dark:outline-white/10">
</div>
