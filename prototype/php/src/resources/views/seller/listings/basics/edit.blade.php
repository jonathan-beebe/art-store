<x-layouts.seller :title="'Your item — '.$listing->title.' — Art Store seller'">
    <p><a href="{{ route('seller.listings.edit', $listing) }}" class="text-gray-700 dark:text-gray-300 underline">&larr; {{ $listing->title }}</a></p>
    <h1 class="mt-2 text-xl font-semibold">Your item</h1>
    <p class="mt-1 max-w-xl text-gray-600 dark:text-gray-400">The basics every buyer sees first.</p>

    <div class="mt-4 grid grid-cols-1 items-start gap-6 lg:grid-cols-[1fr_380px]">
        <div class="flex flex-col gap-4">

            <form method="POST" action="{{ route('seller.listings.update', $listing) }}" class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                @csrf
                @method('PUT')

                <x-form.field name="title" label="Title" required maxlength="255" :value="$listing->title" />

                <x-form.field name="description" label="Description" type="textarea" class="mt-4" rows="4" maxlength="5000"
                              :value="$listing->description" />

                <div class="mt-4">
                    <label for="category_id" class="block font-medium text-gray-700 dark:text-gray-300">Where buyers find it</label>
                    <select id="category_id" name="category_id" class="mt-1 block w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                        <option value="">Uncategorized</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected($listing->category_id === $category->id)>
                                {{ str_repeat('— ', substr_count(trim($category->path, '/'), '/')) }}{{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Gates which item facts the form below asks.</p>
                </div>

                <x-form.field name="dimensions" label="Dimensions" class="mt-4" maxlength="255" :value="$listing->dimensions"
                              hint="The physical piece's own size — separate from any Size choice you offer." />

                @if ($hasOwnPriceAndStock)
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <x-form.field name="price" label="Price" type="number" step="0.01" min="0" required
                                      :value="number_format($listing->price_cents / 100, 2, '.', '')" />

                        <div>
                            <x-form.field name="quantity" label="How many you have" type="number" step="1" min="0" max="999"
                                          :value="$listing->quantity" />
                            <label class="mt-1 flex items-center gap-2 text-gray-700 dark:text-gray-300">
                                <input type="checkbox" name="made_to_order" value="1" @checked($listing->quantity === null)>
                                Made to order — no fixed count
                            </label>
                        </div>
                    </div>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">With "Made to order" checked, the count is ignored and the listing stays available.</p>
                @endif

                <div class="mt-6 flex items-center gap-3">
                    <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Save</button>
                    <a href="{{ route('seller.listings.edit', $listing) }}" class="text-gray-700 dark:text-gray-300 underline">Cancel</a>
                </div>
            </form>

            @if ($attributeGrants->isNotEmpty())
                <form method="POST" action="{{ route('seller.listings.attributes.update', $listing) }}" class="space-y-4 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                    @csrf
                    @method('PUT')

                    @foreach ($attributeGrants as $grant)
                        @php
                            $selected = $listingAttributeSelections[$grant->property_id] ?? [];
                            $isMedium = $grant->property->name === 'Medium';
                        @endphp
                        <div id="attribute-{{ $grant->property_id }}">
                            <label for="attribute-select-{{ $grant->property_id }}" class="block font-medium text-gray-700 dark:text-gray-300">
                                {{ $grant->property->name }}@if ($grant->required)<span class="text-red-700 dark:text-red-400"> *</span>@endif
                            </label>
                            <select id="attribute-select-{{ $grant->property_id }}" name="attribute[{{ $grant->property_id }}][]"
                                    @if ($grant->multivalued) multiple @endif
                                    class="mt-1 block w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                                @unless ($grant->multivalued)
                                    <option value="">— None —</option>
                                @endunless
                                @foreach ($grant->property->values as $value)
                                    <option value="{{ $value->id }}" @selected(in_array($value->id, $selected, true))>{{ $value->label }}</option>
                                @endforeach
                            </select>
                            @if ($isMedium)
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Not on the list? Custom values aren't available yet — say it in the description.</p>
                            @endif
                        </div>
                    @endforeach

                    <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Save facts</button>
                </form>
            @endif
        </div>

        <div>
            <x-seller.buyer-view :listing="$listing" />
        </div>
    </div>
</x-layouts.seller>
