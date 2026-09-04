{{-- Below `sm`: a back link naming the list a detail page came from, above
     the heading. At `sm` and up: hidden — the page's own "All X" link
     (already in its header row) is what today's layout keeps. --}}
@props(['route', 'label'])

<a href="{{ $route }}" class="mb-2 inline-flex min-h-11 items-center gap-1.5 text-stone-600 dark:text-stone-400 hover:text-stone-900 dark:hover:text-stone-100 sm:hidden">
    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M10 4L6 8l4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
    <span>{{ $label }}</span>
</a>
