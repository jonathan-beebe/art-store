@props([
    'accent' => 'stone',
    'label',
])

{{--
    One cell of a shared-borders stat grid: a label above a figure. The
    label wraps rather than truncating, so a long label stays legible; the
    cell is a column flex container with the figure pinned to its bottom
    (`mt-auto`), so a wrapped label grows upward from the figure instead of
    pushing it down — every figure in the row still shares one baseline
    once the grid stretches each cell to the row's tallest label. `min-w-0`
    lets the cell shrink below its content's natural width, which a long
    unbroken word in the figure can still need. The grid itself (columns,
    gap, ring) is the caller's; this is only the cell. Any attribute the
    caller passes (a `data-stat` hook included) lands on the figure, not
    this wrapper.
--}}
<div class="flex min-w-0 flex-col gap-1 bg-white px-4 py-5 sm:p-6 {{ $accent === 'gray' ? 'dark:bg-gray-900' : 'dark:bg-stone-900' }}">
    <p class="text-sm/6 font-medium {{ $accent === 'gray' ? 'text-gray-500 dark:text-gray-400' : 'text-stone-500 dark:text-stone-400' }}">{{ $label }}</p>
    <p {{ $attributes->merge(['class' => 'mt-auto text-2xl font-semibold tracking-tight tabular-nums '.($accent === 'gray' ? 'text-gray-900 dark:text-white' : 'text-stone-900 dark:text-white')]) }}>{{ $slot }}</p>
</div>
