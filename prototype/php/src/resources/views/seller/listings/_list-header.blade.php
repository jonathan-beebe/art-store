{{--
    The list column's header (Listings.dc.html): the section title, its
    total count, and the New listing button that opens the create dialog.
    Shared by the index and show views, same as the rows partial, so the
    list pane reads identically from either route. Included with a `total`
    variable in scope (a plain @include, not a component — it has no
    caller-facing slots or attributes of its own).
--}}
<div class="flex items-center gap-2">
    <h1 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Listings</h1>
    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $total }}</span>
    <button type="button" data-new-listing-open class="ml-auto rounded-md bg-indigo-600 px-2.5 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-indigo-500">New listing</button>
</div>

@include('seller.listings._new-listing-modal')
