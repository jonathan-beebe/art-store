{{--
    The listings tool's one header (04-listings.html): title, count, the
    List/Table/Grid view switch, a sort select on Table and Grid, and New
    listing. Shared by the index route's three views and the detail
    route's overlay/takeover workspace, so a seller reads the same header
    wherever they are. Expects `listingsTotal`, `view`, `viewLinks`, and,
    when `$view->showsSort()`, `sort`, `sortOptions`, `sortFormFields`.
--}}
<div class="flex shrink-0 flex-wrap items-center gap-4 border-b border-gray-200 px-8 py-4 dark:border-white/10">
    <h1 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Listings</h1>
    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $listingsTotal }}</span>

    <div class="inline-flex isolate rounded-md" role="group" aria-label="View">
        @foreach ($viewLinks as $link)
            <a
                href="{{ $link['href'] }}"
                @if ($link['active']) aria-current="true" @endif
                class="relative -ml-px inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium first:ml-0 first:rounded-l-md last:rounded-r-md inset-ring inset-ring-gray-300 dark:inset-ring-white/10 {{ $link['active'] ? 'z-10 bg-indigo-50 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300' : 'bg-white text-gray-600 dark:bg-white/10 dark:text-gray-400' }}"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-4" aria-hidden="true"><path d="{{ \App\Domain\Seller\ListingView::from($link['key'])->iconPath() }}"></path></svg>
                {{ $link['label'] }}
            </a>
        @endforeach
    </div>

    @if ($view->showsSort())
        <form method="GET" action="{{ route('seller.listings.index') }}" class="inline-flex items-center gap-2">
            @foreach ($sortFormFields as $field => $value)
                <input type="hidden" name="{{ $field }}" value="{{ $value }}">
            @endforeach
            <input type="hidden" name="dir" value="desc">
            <label class="inline-flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                Sort
                <select name="sort" onchange="this.form.requestSubmit()" class="rounded-md bg-white py-1 pr-7 pl-2 text-xs text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10">
                    @foreach ($sortOptions as $option)
                        <option value="{{ $option['value'] }}" @selected($option['selected'])>{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </label>
            <noscript><button type="submit" class="rounded-md bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-900 inset-ring inset-ring-gray-300 dark:bg-white/10 dark:text-white dark:inset-ring-white/10">Sort</button></noscript>
        </form>
    @endif

    <button type="button" data-new-listing-open class="ml-auto rounded-md bg-indigo-600 px-2.5 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-indigo-500">New listing</button>
</div>

@include('seller.listings._new-listing-modal')
