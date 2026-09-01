{{--
    Below `lg`, where the detail pane is the whole screen: a way back to the
    list it came from, above the detail heading. At `lg` and up the list
    pane sits beside the detail pane already, so this stays hidden there.
--}}
@props(['route', 'label'])

<a href="{{ $route }}" class="mb-4 inline-flex min-h-11 items-center gap-1.5 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 lg:hidden">
    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M10 4L6 8l4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
    <span>{{ $label }}</span>
</a>
