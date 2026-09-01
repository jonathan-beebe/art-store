{{-- Total-count-based prev/next pager: query-string `page=N`, current
     filters carried through both links. `App\Support\Page` does the
     arithmetic; every list page that grows a pager can reuse this. --}}
@props(['page', 'baseUrl', 'query' => ''])

@if ($page->count > 1)
    <nav aria-label="Pages" class="mt-4 flex items-baseline gap-6 text-stone-700 dark:text-stone-300">
        @unless ($page->isFirst)
            <a rel="prev" href="{{ $baseUrl }}?{{ collect([$query, 'page='.$page->previousNumber])->filter()->implode('&') }}" class="inline-flex min-h-11 items-center underline">Previous</a>
        @endunless
        <span class="text-stone-600 dark:text-stone-400">Page {{ $page->number }} of {{ $page->count }} ({{ number_format($page->totalCount) }} total)</span>
        @unless ($page->isLast)
            <a rel="next" href="{{ $baseUrl }}?{{ collect([$query, 'page='.$page->nextNumber])->filter()->implode('&') }}" class="inline-flex min-h-11 items-center underline">Next</a>
        @endunless
    </nav>
@endif
