@props(['id', 'name', 'label', 'labelClass' => 'block text-sm/6 font-medium text-stone-900 dark:text-stone-100'])

{{-- The one select idiom every admin filter form shares — held here so
     every consumer renders the identical class list rather than each
     carrying its own copy. `labelClass` is the one escape hatch: the logs
     header uses it to go `sr-only` so the label doesn't force a second row,
     without touching the `<select>` itself — the class every consumer must
     still share byte-for-byte. --}}
<div>
    <label for="{{ $id }}" class="{{ $labelClass }}">{{ $label }}</label>
    <select id="{{ $id }}" name="{{ $name }}" class="mt-2 block w-full min-h-11 sm:min-h-0 rounded-md bg-white py-1.5 pl-3 pr-8 text-base text-stone-900 outline-1 -outline-offset-1 outline-stone-300 focus:outline-2 focus:-outline-offset-2 focus:outline-stone-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10">
        {{ $slot }}
    </select>
</div>
