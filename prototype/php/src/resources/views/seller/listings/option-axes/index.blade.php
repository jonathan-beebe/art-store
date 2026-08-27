@php
    use App\Support\Configurator\OptionBuyerPrice;
    use App\Support\Configurator\PriceDifferenceInput;

    // The custom-name form shows whenever there is nothing to catalog-lead
    // with, or the seller asked for it via "Something else…".
    $showCustomChoiceForm = $properties->isEmpty() || request()->query('choice') === 'custom';
@endphp

<x-layouts.seller :title="'Choices you offer — '.$listing->title.' — Art Store seller'">
    <p><a href="{{ route('seller.listings.edit', $listing) }}" class="text-gray-700 dark:text-gray-300 underline">&larr; {{ $listing->title }}</a></p>
    <h1 class="mt-2 text-xl font-semibold">Choices you offer</h1>
    <p class="mt-1 max-w-2xl text-gray-600 dark:text-gray-400">Each choice becomes a dropdown on your listing. A price difference sits on the option itself — set it once and it applies in every other combination.</p>

    <div class="mt-4 grid grid-cols-1 items-start gap-6 lg:grid-cols-[1fr_420px]">
        <div class="flex flex-col gap-4">
            @forelse ($axes as $axis)
                <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                    <div class="flex flex-wrap items-baseline gap-2">
                        <p class="font-semibold text-gray-700 dark:text-gray-300">{{ $axis->name }}</p>

                        @if ($axis->optionValues->contains(fn ($value) => $value->surcharge_cents !== 0))
                            <span class="rounded-full border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-xs">changes the price</span>
                        @else
                            <span class="rounded-full border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-xs">doesn't change the price</span>
                        @endif

                        <form method="POST" action="{{ route('seller.listings.option-axes.destroy', [$listing, $axis]) }}" class="ml-auto">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-gray-700 dark:text-gray-300 underline">Remove this choice</button>
                        </form>
                    </div>

                    <div class="mt-2 flex flex-col divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($axis->optionValues->sortBy('position') as $value)
                            <div class="flex flex-wrap items-center gap-3 py-2">
                                <form method="POST" action="{{ route('seller.listings.option-axes.option-values.update', [$listing, $axis, $value]) }}" class="flex flex-wrap items-center gap-3">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="position" value="{{ $value->position }}">

                                    <label for="is_default-{{ $value->id }}" class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-400">
                                        <input id="is_default-{{ $value->id }}" type="radio" name="is_default" value="1" @checked($value->is_default) class="border-gray-400 dark:border-gray-600">
                                        preselected
                                    </label>

                                    <div>
                                        <label for="label-{{ $value->id }}" class="sr-only">Option label</label>
                                        <input id="label-{{ $value->id }}" type="text" name="label" value="{{ old('label', $value->label) }}" required maxlength="255"
                                               class="w-32 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                                        @error('label')
                                            <p class="mt-1 text-xs text-red-700 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="surcharge-{{ $value->id }}" class="block font-medium text-gray-700 dark:text-gray-300">Price difference</label>
                                        <input id="surcharge-{{ $value->id }}" type="text" name="surcharge" value="{{ old('surcharge', PriceDifferenceInput::format($value->surcharge_cents)) }}"
                                               class="mt-1 w-24 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                                        @error('surcharge')
                                            <p class="mt-1 text-xs text-red-700 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <span class="rounded-full border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-xs">
                                        buyers pay {{ OptionBuyerPrice::forOption($listing->price(), $axis->pricing_mode, $value)->format() }}
                                    </span>

                                    <button type="submit" class="rounded border border-gray-400 dark:border-gray-600 px-3 py-1 text-xs">Save</button>
                                </form>

                                <form method="POST" action="{{ route('seller.listings.option-axes.option-values.destroy', [$listing, $axis, $value]) }}" class="ml-auto">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-gray-700 dark:text-gray-300 underline">Remove</button>
                                </form>
                            </div>
                        @endforeach
                    </div>

                    @if ($axis->optionValues->isNotEmpty() && $axis->optionValues->every(fn ($value) => $value->surcharge_cents === 0))
                        <p class="mt-2 text-gray-600 dark:text-gray-400">A choice with no price differences never touches your price — buyers just pick one.</p>
                    @endif

                    <form method="POST" action="{{ route('seller.listings.option-axes.option-values.store', [$listing, $axis]) }}" class="mt-3 flex flex-wrap items-center gap-3 border-t border-gray-200 dark:border-gray-800 pt-3">
                        @csrf
                        <input type="hidden" name="position" value="{{ $axis->optionValues->isEmpty() ? 0 : $axis->optionValues->max('position') + 1 }}">

                        <label for="new-label-{{ $axis->id }}" class="sr-only">New option label</label>
                        <input id="new-label-{{ $axis->id }}" type="text" name="label" placeholder="New option" required maxlength="255"
                               class="w-32 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">

                        <label for="new-surcharge-{{ $axis->id }}" class="sr-only">Price difference</label>
                        <input id="new-surcharge-{{ $axis->id }}" type="text" name="surcharge" placeholder="+$0.00"
                               class="w-24 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">

                        <button type="submit" class="rounded border border-gray-400 dark:border-gray-600 px-4 py-2">Add option</button>
                    </form>
                </div>
            @empty
                <p class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 text-gray-600 dark:text-gray-400">No choices yet. Add one below — Metal, Size, or a custom label of your own.</p>
            @endforelse

            <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <p class="font-semibold text-gray-700 dark:text-gray-300">Add another choice</p>
                <p class="mt-1 text-gray-600 dark:text-gray-400">Start from what buyers search by, or name your own:</p>

                @php $nextAxisPosition = $axes->isEmpty() ? 0 : $axes->max('position') + 1; @endphp

                <div class="mt-2 flex flex-wrap items-center gap-2">
                    @foreach ($properties as $property)
                        <form method="POST" action="{{ route('seller.listings.option-axes.store', $listing) }}">
                            @csrf
                            <input type="hidden" name="name" value="{{ $property->name }}">
                            <input type="hidden" name="property_id" value="{{ $property->id }}">
                            <input type="hidden" name="position" value="{{ $nextAxisPosition }}">
                            <button type="submit" class="rounded border border-gray-400 dark:border-gray-600 px-3 py-1">
                                {{ $property->name }} <span class="text-gray-600 dark:text-gray-400">&middot; from the catalog, searchable</span>
                            </button>
                        </form>
                    @endforeach

                    @if ($properties->isNotEmpty() && ! $showCustomChoiceForm)
                        <a href="{{ route('seller.listings.option-axes.index', $listing) }}?choice=custom" class="rounded border border-gray-400 dark:border-gray-600 px-3 py-1">Something else...</a>
                    @endif
                </div>

                @if ($showCustomChoiceForm)
                    <form method="POST" action="{{ route('seller.listings.option-axes.store', $listing) }}" class="mt-3 flex flex-wrap items-end gap-3">
                        @csrf
                        <x-form.field name="name" label="Choice name" required maxlength="255" hint="Metal, Size, or a custom label of your own." />
                        <input type="hidden" name="position" value="{{ $nextAxisPosition }}">
                        <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Add choice</button>
                    </form>
                @endif

                <p class="mt-2 text-gray-600 dark:text-gray-400">A catalog choice starts with its standard options filled in; keep the ones you make.</p>
            </div>

            @if ($combinations !== null)
                <div class="flex items-center gap-3 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                    <div>
                        <p class="font-semibold text-gray-700 dark:text-gray-300">The combinations you make</p>
                        <p class="mt-1 text-gray-600 dark:text-gray-400">
                            @if ($combinations['offeredCount'] === $combinations['totalCombinations'])
                                All {{ $combinations['totalCombinations'] }} offered right now.
                            @else
                                {{ $combinations['offeredCount'] }} of {{ $combinations['totalCombinations'] }} offered.
                            @endif
                            Turn off ones you don't make, track stock per combination, or set an exact price for one.
                        </p>
                    </div>
                    <a href="{{ $combinations['combinationsUrl'] }}" class="ml-auto whitespace-nowrap rounded border border-gray-400 dark:border-gray-600 px-3 py-1">Combinations &amp; stock &rarr;</a>
                </div>
            @endif

            <p class="text-gray-600 dark:text-gray-400">Every option ships on this listing's timeline. A per-option timeline ("silver ships tomorrow, gold takes 3 weeks") isn't available yet.</p>
        </div>

        <div>
            <x-seller.buyer-view :listing="$listing" />

            @if ($axes->isNotEmpty())
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-500">Buyers land on this listing with its preselected options already chosen, so the page opens at a concrete price. Picking a different option updates the total before checkout — no surprises at the end.</p>
            @endif
        </div>
    </div>
</x-layouts.seller>
