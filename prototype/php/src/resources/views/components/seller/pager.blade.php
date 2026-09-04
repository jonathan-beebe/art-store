{{--
    Total-count-based prev/next pager, the seller-indigo twin of
    `x-admin.pager`: `page=N` in the query, `query`'s filters carried
    through both links. `App\Support\Page` does the arithmetic;
    `routeName` names the route every other page of the same list opens.
--}}
@props(['page', 'routeName', 'query' => []])

@if ($page->count > 1)
    <nav aria-label="Pages" class="mt-4 flex items-baseline gap-6">
        @unless ($page->isFirst)
            <a rel="prev" href="{{ route($routeName, [...$query, 'page' => $page->previousNumber]) }}" class="text-sm/6 font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">Previous</a>
        @endunless
        <span class="text-sm text-gray-500 dark:text-gray-400">Page {{ $page->number }} of {{ $page->count }} ({{ number_format($page->totalCount) }} total)</span>
        @unless ($page->isLast)
            <a rel="next" href="{{ route($routeName, [...$query, 'page' => $page->nextNumber]) }}" class="text-sm/6 font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">Next</a>
        @endunless
    </nav>
@endif
