{{--
    The listings tool's one header (04-listings.html): title, count, the
    List/Table/Grid view switch, a sort select on Table and Grid, and New
    listing. Shared by the index route's three views and both copies the
    detail route renders (the `inert` workspace behind the `2xl` dialog,
    and the takeover below it), so a seller reads the same header
    wherever they are. Expects `listingsTotal` and `chrome`
    ({@see \App\Seller\ListingsChrome}). `asHeading` renders "Listings"
    as the page's `<h1>` on the index route (the default); the detail
    route passes `false` on both its copies, since the listing's own
    title is that page's heading. `withNewListingDialog` renders the New
    listing dialog alongside the button (the default); a caller that
    includes this header more than once in one response passes `false`
    on every copy but one, so the dialog's id never repeats.
--}}
@props(['listingsTotal', 'chrome', 'asHeading' => true, 'withNewListingDialog' => true])

<div class="flex shrink-0 flex-wrap items-center gap-4 border-b border-gray-200 px-8 py-4 dark:border-white/10">
    @if ($asHeading)
        <h1 data-listings-title class="text-sm font-semibold text-gray-900 dark:text-gray-100">Listings</h1>
    @else
        <p data-listings-title class="text-sm font-semibold text-gray-900 dark:text-gray-100">Listings</p>
    @endif
    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $listingsTotal }}</span>

    <x-seller.segmented :links="$chrome->viewLinks" :icons="$chrome->viewIcons" label="View" />

    @if ($chrome->view->showsSort())
        <form method="GET" action="{{ route('seller.listings.index') }}" data-sort-form class="inline-flex items-center gap-2">
            @foreach ($chrome->sortFormFields as $field => $value)
                <input type="hidden" name="{{ $field }}" value="{{ $value }}">
            @endforeach
            <input type="hidden" name="dir" value="desc">
            <label class="inline-flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                Sort
                <select name="sort" data-sort-select class="rounded-md bg-white py-1 pr-7 pl-2 text-xs text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10">
                    @foreach ($chrome->sortOptions as $column)
                        <option value="{{ $column->value }}" @selected($chrome->sort->isColumn($column))>{{ $column->label() }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" data-sort-submit class="rounded-md bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-900 inset-ring inset-ring-gray-300 dark:bg-white/10 dark:text-white dark:inset-ring-white/10">Sort</button>
        </form>
    @endif

    <button type="button" data-new-listing-open class="ml-auto rounded-md bg-indigo-600 px-2.5 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-indigo-500">New listing</button>
</div>

@if ($withNewListingDialog)
    @include('seller.listings._new-listing-modal')
@endif
