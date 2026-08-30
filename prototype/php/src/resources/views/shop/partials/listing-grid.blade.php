@php
    // The listing set every browse-altitude page renders: home, /medium,
    // /browse, and /search all reach a paginated Listing query down to this
    // one grid, pager, and empty state, so a new dimension of `/{prefix}`
    // pages only needs a query and a title — never a second grid to keep in
    // sync with this one. `$emptyMessage` lets a page speak to why nothing
    // is here in its own voice; the storefront default fits every page that
    // does not pass one.
    $emptyMessage ??= 'No art matches that yet.';
@endphp

@if ($listings->isEmpty())
    <p class="mt-16 text-lg text-ink-muted">{{ $emptyMessage }}</p>
@else
    <p class="mt-10 text-sm text-ink-faint">{{ $listings->total() }} {{ str('work')->plural($listings->total()) }}</p>

    <ul class="mt-4 grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-3">
        @foreach ($listings as $listing)
            <li><x-listing-card :listing="$listing" /></li>
        @endforeach
    </ul>

    <div class="mt-16">{{ $listings->links() }}</div>
@endif
