{{--
    Grid view (04-listings.html): the storefront's own product grid, as
    the seller sees it — square cover, title and price, medium and stock,
    a stats line, the status badge over the image. Rows lead to the
    listing's detail as an overlay or takeover (?from=grid). Expects
    `rows`, `chrome`, `rangeDays`.
--}}
<div class="grid grid-cols-2 gap-x-6 gap-y-10 sm:grid-cols-3 lg:grid-cols-4">
    @forelse ($rows as $row)
        <a href="{{ route('seller.listings.show', ['listing' => $row->id, 'from' => 'grid', 'sort' => $chrome->sort->column->value, 'dir' => $chrome->sort->direction->value, 'range' => $rangeDays]) }}" class="flex min-w-0 flex-col text-left">
            <span class="relative block w-full">
                <img src="{{ $row->imageUrl }}" alt="" class="aspect-square w-full rounded-lg bg-gray-100 object-cover dark:bg-white/5">
                <span class="absolute top-2.5 left-2.5"><x-seller.status-badge :tint="$row->statusTint">{{ $row->statusLabel }}</x-seller.status-badge></span>
            </span>
            <span class="mt-3 flex w-full justify-between gap-2">
                <span class="min-w-0 truncate font-semibold text-gray-900 dark:text-gray-100">{{ $row->title }}</span>
                <span class="flex-none font-semibold text-gray-900 tabular-nums dark:text-gray-100">{{ $row->price()->format() }}</span>
            </span>
            <span class="mt-0.5 block truncate text-xs text-gray-500 dark:text-gray-400">{{ $row->medium ?? 'No medium' }} &middot; {{ $row->stockLabel() }}</span>
            <span class="mt-2 flex gap-3 text-xs text-gray-600 tabular-nums dark:text-gray-400">
                <span>{{ $row->views }} views</span>
                <span>{{ $row->favorites }} favorites</span>
                <span>{{ $row->sold }} sold</span>
            </span>
        </a>
    @empty
        <p class="col-span-full rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 text-gray-600 dark:text-gray-400">No listings yet. Start with a new one.</p>
    @endforelse
</div>
