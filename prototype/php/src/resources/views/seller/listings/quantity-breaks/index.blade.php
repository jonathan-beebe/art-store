<x-layouts.seller :title="'Quantity breaks — '.$listing->title.' — Art Store seller'">
    <div class="flex flex-wrap items-center gap-4">
        <h1 class="text-xl font-semibold">Quantity breaks</h1>
        <a href="{{ route('seller.listings.edit', $listing) }}" class="ml-auto text-gray-700 dark:text-gray-300 underline">Back to listing</a>
    </div>

    <p class="mt-2 text-gray-600 dark:text-gray-400">At a tier's minimum quantity or more, the resolved unit price carries its discount. Up to 10 tiers.</p>

    @if ($quantityBreaks->isEmpty())
        <p class="mt-4 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 text-gray-600 dark:text-gray-400">No tiers yet.</p>
    @else
        <ul class="mt-4 space-y-3">
            @foreach ($quantityBreaks as $break)
                <li class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                    <form method="POST" action="{{ route('seller.listings.quantity-breaks.update', [$listing, $break]) }}" class="flex flex-wrap items-end gap-3">
                        @csrf
                        @method('PUT')

                        <x-form.field name="min_qty" label="Min quantity" type="number" step="1" min="2" required :value="$break->min_qty" />
                        <x-form.field name="discount_bps" label="Discount (basis points)" type="number" step="1" min="1" max="9999" required :value="$break->discount_bps" hint="100 basis points = 1%." />

                        <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Save</button>
                    </form>

                    <form method="POST" action="{{ route('seller.listings.quantity-breaks.destroy', [$listing, $break]) }}" class="mt-2">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded border border-gray-400 dark:border-gray-600 px-3 py-1 text-sm">Remove tier</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif

    <h2 class="mt-6 font-semibold text-gray-700 dark:text-gray-300">Add a tier</h2>

    <form method="POST" action="{{ route('seller.listings.quantity-breaks.store', $listing) }}" class="mt-2 flex flex-wrap items-end gap-3 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
        @csrf

        <x-form.field name="min_qty" label="Min quantity" type="number" step="1" min="2" required />
        <x-form.field name="discount_bps" label="Discount (basis points)" type="number" step="1" min="1" max="9999" required hint="100 basis points = 1%." />

        <button type="submit" class="rounded border border-gray-400 dark:border-gray-600 px-4 py-2">Add tier</button>
    </form>
</x-layouts.seller>
