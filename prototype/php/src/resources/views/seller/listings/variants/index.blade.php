@php
    use App\Domain\Money\Money;
    use App\Support\Configurator\VariantBuyerPrice;

    /** @var \App\Models\Listing $listing */
    /** @var \Illuminate\Support\Collection<int, \App\Models\OptionAxis> $axes */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Variant> $variants */

    $noChoices = $axes->isEmpty();

    // With no choices there is at most one combination — the schema's empty
    // combo key — since a second row would collide with it.
    $onlyVariant = $noChoices ? $variants->first() : null;

    $piecesVariant = $variants->first(fn ($variant) => $variant->is_serialized);
@endphp

<x-layouts.seller-focused :listing="$listing" :title="'Combinations & stock — '.$listing->title.' — Art Store seller'">
    <p><a href="{{ route('seller.listings.option-axes.index', $listing) }}" class="text-gray-700 dark:text-gray-300 underline">← {{ $listing->title }} › Choices</a></p>
    <h1 class="mt-2 text-xl font-semibold">Combinations &amp; stock</h1>
    <p class="mt-1 max-w-2xl text-gray-600 dark:text-gray-400">Buyers can only buy a combination you make. Each one tracks its own stock, and each shows the price a buyer would pay — change it only where your costs really differ.</p>

            @if ($noChoices)
                <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                    <p class="font-semibold text-gray-700 dark:text-gray-300">Every piece one of a kind?</p>
                    <p class="mt-1 text-gray-600 dark:text-gray-400">This listing offers no choices, so there is nothing here to combine. List each piece instead — its own price and condition — and a sold piece comes off the listing by itself.</p>

                    @if ($onlyVariant !== null && $onlyVariant->is_serialized)
                        <a href="{{ route('seller.listings.variants.units.index', [$listing, $onlyVariant]) }}" class="mt-3 inline-block rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">See your pieces</a>
                    @else
                        <form method="POST" action="{{ route('seller.listings.variants.store', $listing) }}" class="mt-3">
                            @csrf
                            <input type="hidden" name="is_serialized" value="1">
                            <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Start listing pieces</button>
                        </form>
                    @endif
                </div>
            @else
                @if ($variants->isEmpty())
                    <p class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 text-gray-600 dark:text-gray-400">No combinations yet — add one below.</p>
                @else
                    <div class="overflow-x-auto rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">
                        <table class="w-full text-left">
                            <caption class="sr-only">Combinations for this listing</caption>
                            <thead class="border-b border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                <tr>
                                    <th scope="col" class="px-3 py-2 font-semibold">Combination</th>
                                    <th scope="col" class="px-3 py-2 font-semibold">Buyers pay</th>
                                    <th scope="col" class="px-3 py-2 font-semibold">In stock</th>
                                    <th scope="col" class="px-3 py-2 font-semibold">Offered</th>
                                    <th scope="col" class="px-3 py-2 font-semibold"><span class="sr-only">Save</span></th>
                                    <th scope="col" class="px-3 py-2 font-semibold"><span class="sr-only">Remove</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                @foreach ($variants as $variant)
                                    @php
                                        $buyersPayFromChoices = VariantBuyerPrice::withoutOverride($listing->price(), $variant);
                                    @endphp
                                    <tr id="{{ $variant->id }}" class="{{ $variant->enabled ? '' : 'bg-gray-50 dark:bg-gray-800/30 text-gray-400 dark:text-gray-600' }}">
                                        <td class="px-3 py-2 font-medium">{{ $variant->comboLabel() }}</td>

                                        <td class="px-3 py-2">
                                            @if (! $variant->enabled)
                                                —
                                            @elseif ($variant->price_override_cents !== null)
                                                <span class="font-medium">{{ Money::fromCents($variant->price_override_cents)->format() }}</span>
                                                <span class="ml-1 rounded-full border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-xs">your price</span>
                                                <form method="POST" action="{{ route('seller.listings.variants.update', [$listing, $variant]) }}" class="inline">
                                                    @csrf
                                                    @method('PUT')
                                                    <input type="hidden" name="sku" value="{{ $variant->sku }}">
                                                    <input type="hidden" name="quantity" value="{{ $variant->quantity }}">
                                                    <input type="hidden" name="enabled" value="1">
                                                    @if ($variant->is_serialized)
                                                        <input type="hidden" name="is_serialized" value="1">
                                                    @endif
                                                    <button type="submit" class="text-xs underline">use {{ $buyersPayFromChoices->format() }}</button>
                                                </form>
                                            @else
                                                <span class="font-medium">{{ $buyersPayFromChoices->format() }}</span>
                                                <span class="ml-1 text-xs text-gray-600 dark:text-gray-400">from choices</span>
                                            @endif
                                        </td>

                                        <td colspan="3" class="px-3 py-2">
                                            <form method="POST" action="{{ route('seller.listings.variants.update', [$listing, $variant]) }}" class="flex flex-wrap items-end gap-3">
                                                @csrf
                                                @method('PUT')

                                                <div>
                                                    <span class="block font-medium text-gray-700 dark:text-gray-300">In stock</span>
                                                    @if ($variant->is_serialized)
                                                        <p class="mt-1"><a href="{{ route('seller.listings.variants.units.index', [$listing, $variant]) }}" class="underline">from pieces: {{ $variant->availableUnitCount() }}</a></p>
                                                    @elseif (! $variant->enabled)
                                                        <input type="hidden" name="quantity" value="{{ $variant->quantity }}">
                                                        <p class="mt-1">—</p>
                                                    @else
                                                        <label for="quantity-{{ $variant->id }}" class="sr-only">In stock</label>
                                                        <input id="quantity-{{ $variant->id }}" name="quantity" type="number" step="1" min="0" value="{{ old('quantity', $variant->quantity) }}" class="mt-1 block w-20 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                                                        @if ($variant->isLowOnStock())
                                                            <span class="font-medium text-amber-700 dark:text-amber-500">low</span>
                                                        @endif
                                                    @endif
                                                </div>

                                                <div>
                                                    <label for="sku-{{ $variant->id }}" class="block font-medium text-gray-700 dark:text-gray-300">Your code</label>
                                                    <input id="sku-{{ $variant->id }}" name="sku" type="text" maxlength="255" value="{{ old('sku', $variant->sku) }}" class="mt-1 block w-32 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                                                </div>

                                                <div>
                                                    <label for="price_override-{{ $variant->id }}" class="block font-medium text-gray-700 dark:text-gray-300">Set your own price</label>
                                                    <input id="price_override-{{ $variant->id }}" name="price_override" type="text" placeholder="from choices" value="{{ old('price_override', $variant->price_override_cents === null ? null : number_format($variant->price_override_cents / 100, 2, '.', '')) }}" class="mt-1 block w-24 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                                                    @error('price_override')
                                                        <p class="mt-1 text-red-700 dark:text-red-400">{{ $message }}</p>
                                                    @enderror
                                                </div>

                                                @if ($variant->is_serialized)
                                                    <input type="hidden" name="is_serialized" value="1">
                                                @endif

                                                <div class="flex items-center gap-2">
                                                    <input id="enabled-{{ $variant->id }}" name="enabled" type="checkbox" value="1" @checked($variant->enabled) class="rounded border-gray-400 dark:border-gray-600">
                                                    <label for="enabled-{{ $variant->id }}" class="text-gray-700 dark:text-gray-300">Offered</label>
                                                    @unless ($variant->enabled)
                                                        <span class="text-xs">you don't make this</span>
                                                    @endunless
                                                </div>

                                                <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Save</button>
                                            </form>
                                        </td>

                                        <td class="px-3 py-2">
                                            <form method="POST" action="{{ route('seller.listings.variants.destroy', [$listing, $variant]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-gray-700 dark:text-gray-300 underline">Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <p class="-mt-1 text-gray-600 dark:text-gray-400">Unchecking "Offered" keeps the row — its stock and price wait for later. A combination you never add simply doesn't exist.</p>

                <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                    <p class="font-semibold text-gray-700 dark:text-gray-300">Add a combination you make</p>

                    @if ($everyCombinationExists)
                        <p class="mt-2 text-gray-600 dark:text-gray-400">Every combination exists — edit rows above.</p>
                    @else
                        <div class="mt-2 flex flex-wrap items-center gap-3">
                            <form method="POST" action="{{ route('seller.listings.variants.store', $listing) }}" class="flex flex-wrap items-end gap-3">
                                @csrf

                                @foreach ($axes as $axis)
                                    <div>
                                        <label for="option_value_id-{{ $axis->id }}" class="block font-medium text-gray-700 dark:text-gray-300">{{ $axis->name }}</label>
                                        <select id="option_value_id-{{ $axis->id }}" name="option_value_id[{{ $axis->id }}]" required class="mt-1 block rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                                            @foreach ($axis->optionValues as $value)
                                                <option value="{{ $value->id }}">{{ $value->label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endforeach

                                <x-form.field name="quantity" label="Stock" type="number" step="1" min="0" value="1" />

                                <button type="submit" class="rounded border border-gray-400 dark:border-gray-600 px-4 py-2">Add it</button>
                            </form>

                            <form method="POST" action="{{ route('seller.listings.variants.generate', $listing) }}" class="ml-auto text-gray-600 dark:text-gray-400">
                                @csrf
                                or <button type="submit" class="underline">add every missing combination</button>
                            </form>
                        </div>
                    @endif

                    <p class="mt-2 text-gray-600 dark:text-gray-400">Only what you add is sold — a size 11 that only exists in gold is one row, and the silver and rose-gold size 11 never appear. Each combination can also carry your own workshop code (SKU) — it shows on your order list, so you know what to pull at a glance.</p>
                </div>

                <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                    <p class="font-semibold text-gray-700 dark:text-gray-300">Change many at once</p>

                    <form method="POST" action="{{ route('seller.listings.variants.bulk', $listing) }}" class="mt-2 flex flex-wrap items-center gap-2">
                        @csrf
                        <span class="text-gray-700 dark:text-gray-300">Where</span>

                        <label for="bulk-option-value" class="sr-only">Choice and option</label>
                        <select id="bulk-option-value" name="option_value_id" required class="rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                            @foreach ($axes as $axis)
                                @foreach ($axis->optionValues as $value)
                                    <option value="{{ $value->id }}">{{ $axis->name }} is {{ $value->label }}</option>
                                @endforeach
                            @endforeach
                        </select>

                        <label for="bulk-enabled" class="sr-only">Set to</label>
                        <select id="bulk-enabled" name="enabled" required class="rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                            <option value="0">stop offering them</option>
                            <option value="1">offer them</option>
                        </select>

                        <button type="submit" class="rounded border border-gray-400 dark:border-gray-600 px-4 py-2">Apply</button>
                    </form>

                    <div class="mt-3 flex flex-wrap items-center gap-2 text-gray-500 dark:text-gray-500">
                        <span>Where</span>
                        <span class="rounded border border-dashed border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40 px-3 py-2">Size is Large</span>
                        <span class="rounded border border-dashed border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40 px-3 py-2">add $10.00 to the price</span>
                        <span class="rounded border border-dashed border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40 px-3 py-2">Apply</span>
                        <x-seller.coming-pill />
                    </div>

                    <p class="mt-2 text-gray-600 dark:text-gray-400">When your material cost rises, sweep the change across combinations instead of retyping each one.</p>
                </div>

                <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                    <p class="font-semibold text-gray-700 dark:text-gray-300">How stock works here</p>
                    <ul class="mt-2 flex flex-col gap-2 text-gray-600 dark:text-gray-400">
                        <li><strong class="text-gray-700 dark:text-gray-300">Made in batches:</strong> give the combination a count. It sells down and shows as unavailable at zero — no cleanup on your end.</li>
                        <li><strong class="text-gray-700 dark:text-gray-300">Made to order:</strong> leave the count blank. The combination just stays available.</li>
                        <li>
                            <strong class="text-gray-700 dark:text-gray-300">One of a kind:</strong> track each piece on the
                            @if ($piecesVariant !== null)
                                <a href="{{ route('seller.listings.variants.units.index', [$listing, $piecesVariant]) }}" class="underline">Individual pieces</a>
                            @else
                                Individual pieces
                            @endif
                            screen — a sold piece comes off the listing by itself.
                        </li>
                    </ul>
                </div>
            @endif

    <x-slot:preview>
        <x-seller.buyer-view :listing="$listing" caption="unavailable options grey out with the reason" />
    </x-slot:preview>
</x-layouts.seller-focused>
