@php
    /** @var \App\Models\Listing $listing */
    /** @var \Illuminate\Support\Collection<int, \App\Models\OptionAxis> $axes */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Variant> $variants */
    $comboLabel = function (\App\Models\Variant $variant): string {
        $labels = $variant->options->map(fn ($option) => $option->optionValue?->label)->filter()->all();

        return $labels === [] ? '(no axes)' : implode(' / ', $labels);
    };
@endphp

<x-layouts.seller :title="'Variants — '.$listing->title.' — Art Store seller'">
    <div class="flex flex-wrap items-center gap-4">
        <h1 class="text-xl font-semibold">Variants</h1>
        <a href="{{ route('seller.listings.edit', $listing) }}" class="ml-auto text-gray-700 dark:text-gray-300 underline">Back to listing</a>
    </div>

    <p class="mt-2 text-gray-600 dark:text-gray-400">Rows exist only for the combinations you create — nothing here is generated automatically unless you ask for it below.</p>

    @if ($variants->isEmpty())
        <p class="mt-4 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 text-gray-600 dark:text-gray-400">No variants yet.</p>
    @else
        <div class="mt-4 overflow-x-auto rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">
            <table class="w-full text-left">
                <caption class="sr-only">Variants for this listing</caption>
                <thead class="border-b border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th scope="col" class="px-3 py-2 font-semibold">Combination</th>
                        <th scope="col" class="px-3 py-2 text-right font-semibold">Derived price</th>
                        <th scope="col" class="px-3 py-2 font-semibold">Override</th>
                        <th scope="col" class="px-3 py-2 font-semibold">SKU</th>
                        <th scope="col" class="px-3 py-2 font-semibold">Qty</th>
                        <th scope="col" class="px-3 py-2 font-semibold">Serialized</th>
                        <th scope="col" class="px-3 py-2 font-semibold">Enabled</th>
                        <th scope="col" class="px-3 py-2 font-semibold">Save</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @foreach ($variants as $variant)
                        <tr id="{{ $variant->id }}">
                            <td class="px-3 py-2">
                                {{ $comboLabel($variant) }}
                                @if ($variant->is_serialized)
                                    <a href="{{ route('seller.listings.variants.units.index', [$listing, $variant]) }}" class="ml-2 text-gray-700 dark:text-gray-300 underline">Units</a>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $variant->resolvedPrice($listing->price())->format() }}</td>
                            <td colspan="6" class="px-3 py-2">
                                <form method="POST" action="{{ route('seller.listings.variants.update', [$listing, $variant]) }}" class="flex flex-wrap items-end gap-3">
                                    @csrf
                                    @method('PUT')

                                    <x-form.field name="price_override" label="Override (dollars)" :value="$variant->price_override_cents === null ? null : number_format($variant->price_override_cents / 100, 2, '.', '')" />
                                    <x-form.field name="sku" label="SKU" maxlength="255" :value="$variant->sku" />

                                    @if ($variant->is_serialized)
                                        <div>
                                            <span class="block font-medium text-gray-700 dark:text-gray-300">Qty (from units)</span>
                                            <p class="mt-1 tabular-nums">{{ $variant->availableUnitCount() }}</p>
                                        </div>
                                    @else
                                        <x-form.field name="quantity" label="Qty" type="number" step="1" min="0" :value="$variant->quantity" />
                                    @endif

                                    <div class="flex items-center gap-2">
                                        <input id="is_serialized-{{ $variant->id }}" name="is_serialized" type="checkbox" value="1" @checked($variant->is_serialized) class="rounded border-gray-400 dark:border-gray-600">
                                        <label for="is_serialized-{{ $variant->id }}" class="text-gray-700 dark:text-gray-300">Serialized</label>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <input id="enabled-{{ $variant->id }}" name="enabled" type="checkbox" value="1" @checked($variant->enabled) class="rounded border-gray-400 dark:border-gray-600">
                                        <label for="enabled-{{ $variant->id }}" class="text-gray-700 dark:text-gray-300">Enabled</label>
                                    </div>

                                    <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Save</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($axes->isNotEmpty())
        <h2 class="mt-6 font-semibold text-gray-700 dark:text-gray-300">Add a combination</h2>

        <form method="POST" action="{{ route('seller.listings.variants.store', $listing) }}" class="mt-2 flex flex-wrap items-end gap-3 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
            @csrf

            @foreach ($axes as $axis)
                <div>
                    <label for="option_value_id-{{ $axis->id }}" class="block font-medium text-gray-700 dark:text-gray-300">{{ $axis->name }}</label>
                    <select id="option_value_id-{{ $axis->id }}" name="option_value_id[{{ $axis->id }}]" required class="mt-1 block w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                        @foreach ($axis->optionValues as $value)
                            <option value="{{ $value->id }}">{{ $value->label }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach

            <x-form.field name="sku" label="SKU" maxlength="255" />
            <x-form.field name="price_override" label="Override (dollars)" />
            <x-form.field name="quantity" label="Qty" type="number" step="1" min="0" value="1" />

            <div class="flex items-center gap-2">
                <input id="new-is-serialized" name="is_serialized" type="checkbox" value="1" class="rounded border-gray-400 dark:border-gray-600">
                <label for="new-is-serialized" class="text-gray-700 dark:text-gray-300">Serialized</label>
            </div>

            <button type="submit" class="rounded border border-gray-400 dark:border-gray-600 px-4 py-2">Add variant</button>
        </form>

        <form method="POST" action="{{ route('seller.listings.variants.generate', $listing) }}" class="mt-4">
            @csrf
            <button type="submit" class="rounded border border-gray-400 dark:border-gray-600 px-4 py-2">Generate every combination</button>
        </form>

        <h2 class="mt-6 font-semibold text-gray-700 dark:text-gray-300">Bulk enable or disable by option</h2>

        <form method="POST" action="{{ route('seller.listings.variants.bulk', $listing) }}" class="mt-2 flex flex-wrap items-end gap-3 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
            @csrf

            <div>
                <label for="bulk-option-value" class="block font-medium text-gray-700 dark:text-gray-300">Option value</label>
                <select id="bulk-option-value" name="option_value_id" required class="mt-1 block w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                    @foreach ($axes as $axis)
                        <optgroup label="{{ $axis->name }}">
                            @foreach ($axis->optionValues as $value)
                                <option value="{{ $value->id }}">{{ $value->label }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="bulk-enabled" class="block font-medium text-gray-700 dark:text-gray-300">Set to</label>
                <select id="bulk-enabled" name="enabled" required class="mt-1 block w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                    <option value="1">Enabled</option>
                    <option value="0">Disabled</option>
                </select>
            </div>

            <button type="submit" class="rounded border border-gray-400 dark:border-gray-600 px-4 py-2">Apply</button>
        </form>
    @endif
</x-layouts.seller>
