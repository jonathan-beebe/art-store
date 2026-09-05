@php
    use App\Configurator\ConfiguratorInput;
    use App\Configurator\QuantityBreakPercent;
    use App\Configurator\QuantityBreakUnitPrice;

    $basePrice = $listing->price();
    $previewQuantity = $quantityBreaks->isEmpty() ? 1 : (int) $quantityBreaks->max('min_qty');
    // The panel opens on the top tier so its discount is visible without
    // any interaction; a live change to the panel's own quantity field
    // round-trips on this URL and overrides this default (IMPRV-015).
    $previewInput = ConfiguratorInput::fromQuery(request(), defaultQuantity: $previewQuantity);
@endphp

<x-layouts.seller-focused :listing="$listing" :title="'Quantity discounts — '.$listing->title.' — Art Store seller'">
    <p><a href="{{ route('seller.listings.edit', $listing) }}" class="text-gray-700 dark:text-gray-300 underline">&larr; {{ $listing->title }}</a></p>
    <h1 class="mt-2 text-xl font-semibold">Quantity discounts</h1>
    <p class="mt-1 max-w-2xl text-gray-600 dark:text-gray-400">Bigger orders pay less per item, the way your print costs actually work — set the breakpoints once and the price drops by itself. No more "Quantity: 50 / 100 / 200" options with hand-typed totals.</p>

            <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                @foreach ($quantityBreaks as $break)
                    <div class="flex flex-wrap items-center gap-2 border-b border-gray-100 dark:border-gray-800 py-3 first:pt-0 last:border-none last:pb-0">
                        <form method="POST" action="{{ route('seller.listings.quantity-breaks.update', [$listing, $break]) }}" class="contents">
                            @csrf
                            @method('PUT')

                            <span class="text-gray-700 dark:text-gray-300">From</span>
                            <label for="min_qty-{{ $break->id }}" class="sr-only">Items</label>
                            <input id="min_qty-{{ $break->id }}" name="min_qty" type="number" step="1" min="2" required
                                   value="{{ old('min_qty', $break->min_qty) }}"
                                   class="w-20 rounded-md border border-gray-400 dark:border-gray-600 px-2 py-1">
                            <span class="text-gray-700 dark:text-gray-300">items,</span>
                            <label for="discount_percent-{{ $break->id }}" class="sr-only">Percent off</label>
                            <input id="discount_percent-{{ $break->id }}" name="discount_percent" type="text" inputmode="decimal" required
                                   value="{{ old('discount_percent', QuantityBreakPercent::format($break->discount_bps)) }}"
                                   class="w-16 rounded-md border border-gray-400 dark:border-gray-600 px-2 py-1">
                            <span class="text-gray-700 dark:text-gray-300">% off each</span>

                            <button type="submit" class="rounded-md border border-gray-400 dark:border-gray-600 px-3 py-1 text-sm">Save</button>

                            @error('min_qty')
                                <span class="w-full text-xs text-red-700 dark:text-red-400">{{ $message }}</span>
                            @enderror
                            @error('discount_percent')
                                <span class="w-full text-xs text-red-700 dark:text-red-400">{{ $message }}</span>
                            @enderror
                        </form>

                        <span class="ml-auto rounded-full border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-xs text-gray-700 dark:text-gray-300">
                            &asymp; {{ QuantityBreakUnitPrice::resolve($basePrice, $break->toDomain())->format() }} per item
                        </span>

                        <form method="POST" action="{{ route('seller.listings.quantity-breaks.destroy', [$listing, $break]) }}" class="contents">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-gray-700 dark:text-gray-300 underline">Remove</button>
                        </form>
                    </div>
                @endforeach

                @if ($quantityBreaks->isEmpty())
                    <p class="text-gray-600 dark:text-gray-400">No breakpoints yet — add the first one below.</p>
                @endif

                <form method="POST" action="{{ route('seller.listings.quantity-breaks.store', $listing) }}" class="mt-3 flex flex-wrap items-center gap-2">
                    @csrf

                    <span class="text-gray-700 dark:text-gray-300">From</span>
                    <label for="new-min_qty" class="sr-only">Items</label>
                    <input id="new-min_qty" name="min_qty" type="number" step="1" min="2" required value="{{ old('min_qty') }}"
                           class="w-20 rounded-md border border-gray-400 dark:border-gray-600 px-2 py-1">
                    <span class="text-gray-700 dark:text-gray-300">items,</span>
                    <label for="new-discount_percent" class="sr-only">Percent off</label>
                    <input id="new-discount_percent" name="discount_percent" type="text" inputmode="decimal" required value="{{ old('discount_percent') }}"
                           class="w-16 rounded-md border border-gray-400 dark:border-gray-600 px-2 py-1">
                    <span class="text-gray-700 dark:text-gray-300">% off each</span>

                    <button type="submit" class="ml-auto rounded-md border border-gray-400 dark:border-gray-600 px-4 py-2">Add a breakpoint</button>
                </form>

                <p class="mt-3 text-gray-600 dark:text-gray-400">Per-item prices shown at your {{ $basePrice->format() }} base. The discount applies to whatever the buyer configures — paper upgrades included.</p>
            </div>

            <p class="text-gray-600 dark:text-gray-400">A private price for one customer isn't available yet — quote bespoke jobs in Messages rather than publishing them as options anyone can buy.</p>

    <x-slot:preview>
        <x-seller.buyer-view :listing="$listing" :input="$previewInput" />
        <p class="text-xs text-gray-500 dark:text-gray-500">The active tier is bold, and the total is the exact amount charged — the platform's own quantity field stays meaningful.</p>
    </x-slot:preview>
</x-layouts.seller-focused>
