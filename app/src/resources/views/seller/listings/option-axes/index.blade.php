@php
    use App\Domain\Configurator\PricingMode;
    use App\Configurator\OptionBuyerPrice;
    use App\Configurator\PriceDifferenceInput;

    // The custom-name form shows whenever there is nothing to catalog-lead
    // with, or the seller asked for it via "Something else…".
    $showCustomChoiceForm = $properties->isEmpty() || request()->query('choice') === 'custom';

    // "Add another choice" is mode-first (DSGN-002): the seller picks how a
    // new choice prices its options before seeing any catalog-property or
    // custom-name picker, threaded through as a GET `mode` param so the
    // whole flow works with JS off.
    $selectedMode = match (request()->query('mode')) {
        PricingMode::Standalone->value => PricingMode::Standalone,
        PricingMode::AddOn->value => PricingMode::AddOn,
        default => null,
    };
    $addChoiceUrl = route('seller.listings.option-axes.index', $listing);
@endphp

<x-layouts.seller-focused :listing="$listing" :title="'Choices you offer — '.$listing->title.' — Art Store seller'">
    <p><a href="{{ route('seller.listings.edit', $listing) }}" class="text-gray-700 dark:text-gray-300 underline">&larr; {{ $listing->title }}</a></p>
    <h1 class="mt-2 text-xl font-semibold">Choices you offer</h1>
    <p class="mt-1 max-w-2xl text-gray-600 dark:text-gray-400">Each choice becomes a dropdown on your listing. Say up front whether its options are each priced on their own, or add to your price — every option follows that choice's rule.</p>

            @forelse ($axes as $axis)
                @php $isStandalone = $axis->pricing_mode === PricingMode::Standalone; @endphp
                <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                    <div class="flex flex-wrap items-baseline gap-2">
                        <p class="font-semibold text-gray-700 dark:text-gray-300">{{ $axis->name }}</p>

                        @include('seller.listings.option-axes._mode-pill', ['mode' => $axis->pricing_mode])

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
                                        <input id="is_default-{{ $value->id }}" type="checkbox" name="is_default" value="1" @checked($value->is_default) class="rounded border-gray-400 dark:border-gray-600">
                                        preselected — saving clears any other preselected option
                                    </label>

                                    <div>
                                        <label for="label-{{ $value->id }}" class="sr-only">Option label</label>
                                        <input id="label-{{ $value->id }}" type="text" name="label" value="{{ old('label', $value->label) }}" required maxlength="255"
                                               class="w-32 rounded-md border border-gray-400 dark:border-gray-600 px-3 py-2">
                                        @error('label')
                                            <p class="mt-1 text-xs text-red-700 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    @if ($isStandalone)
                                        <div>
                                            <label for="price-{{ $value->id }}" class="block font-medium text-gray-700 dark:text-gray-300">Price</label>
                                            <div class="relative mt-1">
                                                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-500 dark:text-gray-400">$</span>
                                                <input id="price-{{ $value->id }}" type="text" name="price"
                                                       value="{{ old('price', number_format(($value->price_cents ?? 0) / 100, 2, '.', ',')) }}" required
                                                       class="w-24 rounded-md border border-gray-400 dark:border-gray-600 py-2 pl-6 pr-3">
                                            </div>
                                            @error('price')
                                                <p class="mt-1 text-xs text-red-700 dark:text-red-400">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    @else
                                        <div>
                                            <label for="surcharge-{{ $value->id }}" class="block font-medium text-gray-700 dark:text-gray-300">Price difference</label>
                                            <input id="surcharge-{{ $value->id }}" type="text" name="surcharge" value="{{ old('surcharge', PriceDifferenceInput::format($value->surcharge_cents)) }}"
                                                   class="mt-1 w-24 rounded-md border border-gray-400 dark:border-gray-600 px-3 py-2">
                                            @error('surcharge')
                                                <p class="mt-1 text-xs text-red-700 dark:text-red-400">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <span class="rounded-full border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-xs">
                                            buyers pay {{ OptionBuyerPrice::forOption($listing->price(), $axis->pricing_mode, $value)->format() }}
                                        </span>
                                    @endif

                                    <button type="submit" class="rounded-md border border-gray-400 dark:border-gray-600 px-3 py-1 text-xs">Save</button>
                                </form>

                                <form method="POST" action="{{ route('seller.listings.option-axes.option-values.destroy', [$listing, $axis, $value]) }}" class="ml-auto">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-gray-700 dark:text-gray-300 underline">Remove</button>
                                </form>
                            </div>
                        @endforeach
                    </div>

                    @if ($axis->optionValues->isNotEmpty())
                        @if ($isStandalone)
                            <p class="mt-2 text-gray-600 dark:text-gray-400">No option is the "base" here — each size just costs what it costs. Buyers pay exactly the price shown for the size they pick.</p>
                        @elseif ($axis->optionValues->every(fn ($value) => $value->surcharge_cents === 0))
                            <p class="mt-2 text-gray-600 dark:text-gray-400">A choice with no price differences never touches your price — buyers just pick one.</p>
                        @else
                            <p class="mt-2 text-gray-600 dark:text-gray-400">Buyers pay your item's price, plus whatever {{ $axis->name }} adds on top.</p>
                        @endif
                    @endif

                    <form method="POST" action="{{ route('seller.listings.option-axes.option-values.store', [$listing, $axis]) }}" class="mt-3 flex flex-wrap items-center gap-3 border-t border-gray-200 dark:border-gray-800 pt-3">
                        @csrf
                        <input type="hidden" name="position" value="{{ $axis->optionValues->isEmpty() ? 0 : $axis->optionValues->max('position') + 1 }}">

                        <label for="new-label-{{ $axis->id }}" class="sr-only">New option label</label>
                        <input id="new-label-{{ $axis->id }}" type="text" name="label" placeholder="New option" required maxlength="255"
                               class="w-32 rounded-md border border-gray-400 dark:border-gray-600 px-3 py-2">

                        @if ($isStandalone)
                            <label for="new-price-{{ $axis->id }}" class="sr-only">Price</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-500 dark:text-gray-400">$</span>
                                <input id="new-price-{{ $axis->id }}" type="text" name="price" placeholder="18.00" required
                                       class="w-24 rounded-md border border-gray-400 dark:border-gray-600 py-2 pl-6 pr-3">
                            </div>
                        @else
                            <label for="new-surcharge-{{ $axis->id }}" class="sr-only">Price difference</label>
                            <input id="new-surcharge-{{ $axis->id }}" type="text" name="surcharge" placeholder="+$0.00"
                                   class="w-24 rounded-md border border-gray-400 dark:border-gray-600 px-3 py-2">
                        @endif

                        <button type="submit" class="rounded-md border border-gray-400 dark:border-gray-600 px-4 py-2">Add option</button>
                    </form>
                </div>
            @empty
                <p class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 text-gray-600 dark:text-gray-400">No choices yet. Add one below — Metal, Size, or a custom label of your own.</p>
            @endforelse

            <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                @if ($selectedMode === null)
                    <p class="font-semibold text-gray-700 dark:text-gray-300">Add another choice</p>
                    <p class="mt-1 text-gray-600 dark:text-gray-400">Pick how its options get priced — you can't change this after adding the first option, so choose the one that matches how you actually price it.</p>

                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <a href="{{ $addChoiceUrl }}?mode=standalone" class="block rounded-md border border-gray-400 dark:border-gray-600 px-4 py-3 text-left font-medium text-gray-900 dark:text-gray-100">
                            Each option priced on its own
                            <span class="mt-0.5 block font-normal text-gray-600 dark:text-gray-400">Small, medium, large — every size just has a price. Nothing is a "base."</span>
                        </a>
                        <a href="{{ $addChoiceUrl }}?mode=add_on" class="block rounded-md border border-gray-400 dark:border-gray-600 px-4 py-3 text-left font-medium text-gray-900 dark:text-gray-100">
                            Options add to your price
                            <span class="mt-0.5 block font-normal text-gray-600 dark:text-gray-400">A frame, an engraving, a nicer paper — each one adds a little (or a lot) to what you already charge.</span>
                        </a>
                    </div>
                @else
                    <div class="flex flex-wrap items-baseline gap-2">
                        <p class="font-semibold text-gray-700 dark:text-gray-300">Add another choice</p>
                        @include('seller.listings.option-axes._mode-pill', ['mode' => $selectedMode])
                        <a href="{{ $addChoiceUrl }}" class="ml-auto underline">Choose a different pricing style</a>
                    </div>
                    <p class="mt-1 text-gray-600 dark:text-gray-400">Start from what buyers search by, or name your own:</p>

                    @php $nextAxisPosition = $axes->isEmpty() ? 0 : $axes->max('position') + 1; @endphp

                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        @foreach ($properties as $property)
                            <form method="POST" action="{{ route('seller.listings.option-axes.store', $listing) }}">
                                @csrf
                                <input type="hidden" name="name" value="{{ $property->name }}">
                                <input type="hidden" name="property_id" value="{{ $property->id }}">
                                <input type="hidden" name="position" value="{{ $nextAxisPosition }}">
                                <input type="hidden" name="pricing_mode" value="{{ $selectedMode->value }}">
                                <button type="submit" class="rounded-md border border-gray-400 dark:border-gray-600 px-3 py-1">
                                    {{ $property->name }} <span class="text-gray-600 dark:text-gray-400">&middot; from the catalog, searchable</span>
                                </button>
                            </form>
                        @endforeach

                        @if ($properties->isNotEmpty() && ! $showCustomChoiceForm)
                            <a href="{{ $addChoiceUrl }}?mode={{ $selectedMode->value }}&choice=custom" class="rounded-md border border-gray-400 dark:border-gray-600 px-3 py-1">Something else...</a>
                        @endif
                    </div>

                    @if ($showCustomChoiceForm)
                        <form method="POST" action="{{ route('seller.listings.option-axes.store', $listing) }}" class="mt-3 flex flex-wrap items-end gap-3">
                            @csrf
                            <x-form.field name="name" label="Choice name" required maxlength="255" hint="Metal, Size, or a custom label of your own." />
                            <input type="hidden" name="position" value="{{ $nextAxisPosition }}">
                            <input type="hidden" name="pricing_mode" value="{{ $selectedMode->value }}">
                            <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Add choice</button>
                        </form>
                    @endif

                    @if ($selectedMode === PricingMode::Standalone)
                        <p class="mt-2 text-gray-600 dark:text-gray-400">A catalog choice links to your catalog property, searchable — you still give each option its own price yourself.</p>
                    @else
                        <p class="mt-2 text-gray-600 dark:text-gray-400">A catalog choice starts with its standard options filled in; keep the ones you make.</p>
                    @endif
                @endif
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
                    <a href="{{ $combinations['combinationsUrl'] }}" class="ml-auto whitespace-nowrap rounded-md border border-gray-400 dark:border-gray-600 px-3 py-1">Combinations &amp; stock &rarr;</a>
                </div>
            @endif

            <p class="text-gray-600 dark:text-gray-400">Every option ships on this listing's timeline. A per-option timeline ("silver ships tomorrow, gold takes 3 weeks") isn't available yet.</p>

    <x-slot:preview>
        <x-seller.buyer-view :listing="$listing" />

        @if ($axes->isNotEmpty())
            <p class="mt-4 text-sm text-gray-500 dark:text-gray-500">Buyers land on this listing with its preselected options already chosen, so the page opens at a concrete price. Picking a different option updates the total before checkout — no surprises at the end.</p>
        @endif
    </x-slot:preview>
</x-layouts.seller-focused>
