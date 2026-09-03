{{--
    A list pane's row (Tailwind's stacked-list "with links" block): a
    leading visual, a title over a supporting line, a right-aligned meta
    column, and a trailing chevron, in one portal's accent. The nine list
    panes (admin: orders, fulfillments, listings, sellers, customers,
    messages; seller: listings, orders, messages) render every row through
    this component so the shape can't drift pane to pane — each caller only
    fills the slots. `leading`, `supporting`, `preview`, and `meta` are
    optional; `title` is the one slot every row fills. Anything the caller
    passes beyond `accent` and `selected` (`href`, `data-pane-cell`,
    `aria-current`, ...) lands on the row's own anchor via attribute
    passthrough.
--}}
@props([
    'accent' => 'stone',
    'selected' => false,
])

@php
    // One literal Tailwind class per token, spelled out per accent rather
    // than built from `$accent` at runtime — the build's class scan only
    // sees names that appear whole in the source.
    [$hoverBg, $selectedBg, $rail, $chevron, $focusOutline] = $accent === 'indigo'
        ? [
            'hover:bg-gray-50 dark:hover:bg-white/5',
            'bg-gray-50 dark:bg-white/5',
            'shadow-[inset_2px_0_0_0_#4f46e5] dark:shadow-[inset_2px_0_0_0_#6366f1]',
            'text-indigo-400',
            'focus-visible:outline-indigo-600',
        ]
        : [
            'hover:bg-stone-50 dark:hover:bg-white/5',
            'bg-stone-50 dark:bg-white/5',
            'shadow-[inset_2px_0_0_0_var(--color-stone-500)] dark:shadow-[inset_2px_0_0_0_var(--color-stone-400)]',
            'text-stone-400',
            'focus-visible:outline-stone-600',
        ];

    // Every row idiom elsewhere in the app pairs a negative outline-offset
    // with an accent-colored focus-visible outline (composer.blade.php,
    // filters.blade.php, ...) — the seller inbox carried the same pair
    // before this row replaced its own `<a>`, and lost it in the move.
    $rowClasses = trim('flex justify-between gap-x-6 px-6 py-5 -outline-offset-2 focus-visible:outline-2 '.$focusOutline.' '.$hoverBg.($selected ? ' '.$selectedBg.' '.$rail : ''));
@endphp

<a {{ $attributes->merge(['class' => $rowClasses]) }}>
    <div class="flex min-w-0 gap-x-4">
        @isset($leading)
            {{ $leading }}
        @endisset

        <div class="min-w-0 flex-auto">
            {{ $title }}
            @isset($supporting)
                {{ $supporting }}
            @endisset
            @isset($preview)
                {{ $preview }}
            @endisset
        </div>
    </div>

    <div class="flex shrink-0 items-center gap-x-4">
        @isset($meta)
            <div class="hidden sm:flex sm:flex-col sm:items-end">
                {{ $meta }}
            </div>
        @endisset

        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" data-row-chevron class="size-5 flex-none {{ $chevron }}">
            <path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
        </svg>
    </div>
</a>
