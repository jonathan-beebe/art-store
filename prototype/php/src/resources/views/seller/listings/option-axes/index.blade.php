<x-layouts.seller :title="'Axes & options — '.$listing->title.' — Art Store seller'">
    <div class="flex flex-wrap items-center gap-4">
        <h1 class="text-xl font-semibold">Axes &amp; options</h1>
        <a href="{{ route('seller.listings.edit', $listing) }}" class="ml-auto text-gray-700 dark:text-gray-300 underline">Back to listing</a>
    </div>

    @if ($axes->isEmpty())
        <p class="mt-4 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 text-gray-600 dark:text-gray-400">No axes yet. Add one below — Metal, Size, or a custom label of your own.</p>
    @else
        <ul class="mt-4 space-y-6">
            @foreach ($axes as $axis)
                <li class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                    <form method="POST" action="{{ route('seller.listings.option-axes.update', [$listing, $axis]) }}" class="flex flex-wrap items-end gap-3">
                        @csrf
                        @method('PUT')

                        <x-form.field name="name" label="Axis name" required maxlength="255" :value="$axis->name" />

                        <div>
                            <label for="property_id-{{ $axis->id }}" class="block font-medium text-gray-700 dark:text-gray-300">Catalog property</label>
                            <select id="property_id-{{ $axis->id }}" name="property_id" class="mt-1 block w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                                <option value="">Custom label</option>
                                @foreach ($properties as $property)
                                    <option value="{{ $property->id }}" @selected($axis->property_id === $property->id)>{{ $property->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <x-form.field name="position" label="Position" type="number" step="1" min="0" required :value="$axis->position" />

                        <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Save</button>
                    </form>

                    <form method="POST" action="{{ route('seller.listings.option-axes.destroy', [$listing, $axis]) }}" class="mt-2">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded border border-gray-400 dark:border-gray-600 px-3 py-1 text-sm">Remove axis</button>
                    </form>

                    <h3 class="mt-4 font-semibold text-gray-700 dark:text-gray-300">Options</h3>

                    @if ($axis->optionValues->isEmpty())
                        <p class="mt-2 text-gray-600 dark:text-gray-400">No options yet.</p>
                    @else
                        <ul class="mt-2 space-y-2">
                            @foreach ($axis->optionValues as $value)
                                <li class="rounded border border-gray-200 dark:border-gray-800 p-3">
                                    <form method="POST" action="{{ route('seller.listings.option-axes.option-values.update', [$listing, $axis, $value]) }}" class="flex flex-wrap items-end gap-3">
                                        @csrf
                                        @method('PUT')

                                        <x-form.field name="label" label="Label" required maxlength="255" :value="$value->label" />
                                        <x-form.field name="surcharge" label="Surcharge (dollars)" :value="number_format($value->surcharge_cents / 100, 2, '.', '')" />

                                        <div class="flex items-center gap-2">
                                            <input id="is_default-{{ $value->id }}" name="is_default" type="checkbox" value="1" @checked($value->is_default) class="rounded border-gray-400 dark:border-gray-600">
                                            <label for="is_default-{{ $value->id }}" class="text-gray-700 dark:text-gray-300">Default</label>
                                        </div>

                                        <x-form.field name="position" label="Position" type="number" step="1" min="0" required :value="$value->position" />

                                        <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Save</button>
                                    </form>

                                    <form method="POST" action="{{ route('seller.listings.option-axes.option-values.destroy', [$listing, $axis, $value]) }}" class="mt-2">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded border border-gray-400 dark:border-gray-600 px-3 py-1 text-sm">Remove option</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <form method="POST" action="{{ route('seller.listings.option-axes.option-values.store', [$listing, $axis]) }}" class="mt-4 flex flex-wrap items-end gap-3 border-t border-gray-200 dark:border-gray-800 pt-4">
                        @csrf

                        <x-form.field name="label" label="New option label" required maxlength="255" />
                        <x-form.field name="surcharge" label="Surcharge (dollars)" value="0.00" />

                        <div class="flex items-center gap-2">
                            <input id="new-is-default-{{ $axis->id }}" name="is_default" type="checkbox" value="1" class="rounded border-gray-400 dark:border-gray-600">
                            <label for="new-is-default-{{ $axis->id }}" class="text-gray-700 dark:text-gray-300">Default</label>
                        </div>

                        <x-form.field name="position" label="Position" type="number" step="1" min="0" value="0" required />

                        <button type="submit" class="rounded border border-gray-400 dark:border-gray-600 px-4 py-2">Add option</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif

    <h2 class="mt-6 font-semibold text-gray-700 dark:text-gray-300">Add an axis</h2>

    <form method="POST" action="{{ route('seller.listings.option-axes.store', $listing) }}" class="mt-2 flex flex-wrap items-end gap-3 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
        @csrf

        <x-form.field name="name" label="Axis name" required maxlength="255" hint="Metal, Size, or a custom label of your own." />

        <div>
            <label for="property_id" class="block font-medium text-gray-700 dark:text-gray-300">Catalog property</label>
            <select id="property_id" name="property_id" class="mt-1 block w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                <option value="">Custom label</option>
                @foreach ($properties as $property)
                    <option value="{{ $property->id }}">{{ $property->name }}</option>
                @endforeach
            </select>
        </div>

        <x-form.field name="position" label="Position" type="number" step="1" min="0" value="0" required />

        <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Add axis</button>
    </form>
</x-layouts.seller>
