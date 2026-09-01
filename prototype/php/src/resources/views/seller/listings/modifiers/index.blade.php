@php
    use App\Domain\Configurator\ModifierKind;
    use App\Support\Configurator\ModifierKindWord;
    use App\Support\Configurator\PriceDifferenceInput;
    use App\Support\Configurator\ScopedListingPreview;

    $nextModifierPosition = $modifiers->isEmpty() ? 0 : $modifiers->max('position') + 1;
@endphp

<x-layouts.seller-focused :listing="$listing" :title="'Questions you ask the buyer — '.$listing->title.' — Art Store seller'">
    <p><a href="{{ route('seller.listings.edit', $listing) }}" class="text-gray-700 dark:text-gray-300 underline">&larr; {{ $listing->title }}</a></p>
    <h1 class="mt-2 text-xl font-semibold">Questions you ask the buyer</h1>
    <p class="mt-1 max-w-2xl text-gray-600 dark:text-gray-400">
        Answers arrive attached to the order line &mdash; you'll see them where you fulfill, never buried in a message thread.
        A question can charge for the work it asks for, and it only appears when it applies.
    </p>

            @foreach ($modifiers as $modifier)
                <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                    <form method="POST" action="{{ route('seller.listings.modifiers.update', [$listing, $modifier]) }}" class="flex flex-col gap-3">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="position" value="{{ $modifier->position }}">

                        <div class="flex flex-wrap items-baseline gap-3">
                            <p class="font-semibold text-gray-700 dark:text-gray-300">&ldquo;{{ $modifier->prompt }}&rdquo;</p>

                            <label for="kind-{{ $modifier->id }}" class="sr-only">Kind of question</label>
                            <select id="kind-{{ $modifier->id }}" name="kind" class="rounded-full border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-xs">
                                @foreach (ModifierKind::cases() as $kind)
                                    <option value="{{ $kind->value }}" @selected($modifier->kind === $kind)>{{ ModifierKindWord::forKind($kind) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="prompt-{{ $modifier->id }}" class="block font-medium text-gray-700 dark:text-gray-300">The question</label>
                            <input id="prompt-{{ $modifier->id }}" name="prompt" type="text" required maxlength="255" value="{{ old('prompt', $modifier->prompt) }}"
                                   class="mt-1 block w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                        </div>

                        <div>
                            <label for="instructions-{{ $modifier->id }}" class="block font-medium text-gray-700 dark:text-gray-300">
                                A note under it <span class="font-normal text-gray-600 dark:text-gray-400">&mdash; optional</span>
                            </label>
                            <input id="instructions-{{ $modifier->id }}" name="instructions" type="text" value="{{ old('instructions', $modifier->instructions) }}"
                                   class="mt-1 block w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                        </div>

                        @if ($modifier->kind === ModifierKind::Text)
                            <div class="flex flex-wrap items-end gap-6">
                                <div>
                                    <label for="char_limit-{{ $modifier->id }}" class="block font-medium text-gray-700 dark:text-gray-300">Longest answer</label>
                                    <div class="mt-1 flex items-center gap-2">
                                        <input id="char_limit-{{ $modifier->id }}" name="char_limit" type="number" step="1" min="1" value="{{ old('char_limit', $modifier->char_limit) }}"
                                               class="w-20 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                                        <span class="text-gray-600 dark:text-gray-400">letters &mdash; buyers see the limit before they type.</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-end gap-6">
                                <div>
                                    <label for="add_on_price-{{ $modifier->id }}" class="block font-medium text-gray-700 dark:text-gray-300">Extra charge for this</label>
                                    <div class="mt-1 flex items-center gap-2">
                                        <input id="add_on_price-{{ $modifier->id }}" name="add_on_price" type="text" value="{{ old('add_on_price', PriceDifferenceInput::format($modifier->add_on_price_cents)) }}"
                                               class="w-24 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                                        <span class="text-gray-600 dark:text-gray-400">buyers who don't see this question never pay it</span>
                                    </div>
                                </div>

                                <label class="flex items-center gap-2 pb-2">
                                    <input name="required" type="checkbox" value="1" @checked($modifier->required) class="rounded border-gray-400 dark:border-gray-600">
                                    <span class="text-gray-700 dark:text-gray-300">Buyers must answer before they can buy</span>
                                </label>
                            </div>
                        @elseif ($modifier->kind === ModifierKind::Select)
                            <p class="text-gray-600 dark:text-gray-400">Only what's on your list can be picked &mdash; no more requests for a color you've never stocked.</p>

                            <label class="flex items-center gap-2">
                                <input name="required" type="checkbox" value="1" @checked($modifier->required) class="rounded border-gray-400 dark:border-gray-600">
                                <span class="text-gray-700 dark:text-gray-300">Buyers must answer before they can buy</span>
                            </label>
                        @else
                            <div class="flex flex-wrap items-end gap-4">
                                <div>
                                    <label for="unit-{{ $modifier->id }}" class="block font-medium text-gray-700 dark:text-gray-300">Unit</label>
                                    <input id="unit-{{ $modifier->id }}" name="unit" type="text" value="{{ old('unit', $modifier->unit) }}"
                                           class="mt-1 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                                    <p class="mt-1 text-gray-600 dark:text-gray-400">Inches, cm, mm&hellip;</p>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-end gap-4">
                                <div>
                                    <label for="min_value-{{ $modifier->id }}" class="block font-medium text-gray-700 dark:text-gray-300">Smallest allowed</label>
                                    <input id="min_value-{{ $modifier->id }}" name="min_value" type="number" step="any" value="{{ old('min_value', $modifier->min_value) }}"
                                           class="mt-1 w-24 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                                </div>
                                <div>
                                    <label for="max_value-{{ $modifier->id }}" class="block font-medium text-gray-700 dark:text-gray-300">Largest allowed</label>
                                    <input id="max_value-{{ $modifier->id }}" name="max_value" type="number" step="any" value="{{ old('max_value', $modifier->max_value) }}"
                                           class="mt-1 w-24 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                                </div>
                                <span class="pb-2 text-gray-600 dark:text-gray-400">within limits you set</span>
                            </div>

                            <div class="flex flex-wrap items-end gap-6">
                                <div>
                                    <label for="rate-{{ $modifier->id }}" class="block font-medium text-gray-700 dark:text-gray-300">
                                        $ per {{ $modifier->unit ?? 'unit' }}
                                    </label>
                                    <input id="rate-{{ $modifier->id }}" name="rate" type="text"
                                           value="{{ old('rate', $modifier->rate_cents_per_unit === null ? '' : PriceDifferenceInput::format($modifier->rate_cents_per_unit)) }}"
                                           class="mt-1 w-24 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                                </div>

                                <label class="flex items-center gap-2 pb-2">
                                    <input name="required" type="checkbox" value="1" @checked($modifier->required) class="rounded border-gray-400 dark:border-gray-600">
                                    <span class="text-gray-700 dark:text-gray-300">Buyers must answer before they can buy</span>
                                </label>
                            </div>
                        @endif

                        <div class="flex items-center gap-3">
                            <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Save</button>
                        </div>
                    </form>

                    @if ($modifier->kind === ModifierKind::Select)
                        <div class="mt-4 border-t border-gray-100 dark:border-gray-800 pt-4">
                            <div class="flex flex-col gap-2">
                                @foreach ($modifier->options->sortBy('position') as $option)
                                    <div class="flex flex-wrap items-center gap-3">
                                        <div class="h-11 w-11 shrink-0 rounded border border-dashed border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50"></div>

                                        <form method="POST" action="{{ route('seller.listings.modifiers.options.update', [$listing, $modifier, $option]) }}" class="contents">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="position" value="{{ $option->position }}">

                                            <label for="label-{{ $option->id }}" class="sr-only">Option label</label>
                                            <input id="label-{{ $option->id }}" name="label" type="text" required maxlength="255" value="{{ old('label', $option->label) }}"
                                                   class="w-40 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">

                                            <label for="add_on_price-{{ $option->id }}" class="sr-only">Price difference</label>
                                            <input id="add_on_price-{{ $option->id }}" name="add_on_price" type="text" value="{{ old('add_on_price', PriceDifferenceInput::format($option->add_on_price_cents)) }}"
                                                   class="w-24 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">

                                            <button type="submit" class="rounded border border-gray-400 dark:border-gray-600 px-3 py-1 text-sm">Save</button>
                                        </form>

                                        <form method="POST" action="{{ route('seller.listings.modifiers.options.destroy', [$listing, $modifier, $option]) }}" class="contents">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ml-auto text-gray-700 dark:text-gray-300 underline">Remove</button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>

                            <p class="mt-3 text-gray-600 dark:text-gray-400">
                                The squares are where each option's preview image goes &mdash;
                                <x-seller.coming-pill />.
                                Until then buyers see the names only; keep specimens in your listing photos.
                            </p>

                            <form method="POST" action="{{ route('seller.listings.modifiers.options.store', [$listing, $modifier]) }}" class="mt-3 flex flex-wrap items-center gap-3">
                                @csrf
                                @php $nextOptionPosition = $modifier->options->isEmpty() ? 0 : $modifier->options->max('position') + 1; @endphp
                                <input type="hidden" name="position" value="{{ $nextOptionPosition }}">

                                <label for="new-label-{{ $modifier->id }}" class="sr-only">New option</label>
                                <input id="new-label-{{ $modifier->id }}" name="label" type="text" required maxlength="255" placeholder="New option"
                                       class="w-40 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">

                                <label for="new-add_on_price-{{ $modifier->id }}" class="sr-only">Price difference</label>
                                <input id="new-add_on_price-{{ $modifier->id }}" name="add_on_price" type="text" placeholder="+$0.00" value="0.00"
                                       class="w-24 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">

                                <button type="submit" class="rounded border border-gray-400 dark:border-gray-600 px-4 py-2">Add option</button>
                            </form>
                        </div>
                    @endif

                    @if ($axes->isNotEmpty())
                        @php
                            $scoped = $modifier->scopes->pluck('option_value_id')->all();
                            $isScoped = $scoped !== [];
                            $unaffectedLabel = ScopedListingPreview::unaffectedOptionLabel($modifier);
                        @endphp

                        <form method="POST" action="{{ route('seller.listings.modifiers.scope', [$listing, $modifier]) }}" class="mt-4 border-t border-gray-100 dark:border-gray-800 pt-4">
                            @csrf

                            <fieldset>
                                <legend class="font-medium text-gray-700 dark:text-gray-300">When to ask it</legend>
                                <div class="mt-2 flex flex-wrap items-center gap-4">
                                    <label class="flex items-center gap-2">
                                        <input type="radio" name="when" value="always" @checked(! $isScoped) class="border-gray-400 dark:border-gray-600">
                                        <span>Always</span>
                                    </label>
                                    <label class="flex items-center gap-2">
                                        <input type="radio" name="when" value="only" @checked($isScoped) class="border-gray-400 dark:border-gray-600">
                                        <span>Only when</span>
                                    </label>
                                </div>
                            </fieldset>

                            @foreach ($axes as $axis)
                                <fieldset class="mt-2">
                                    <legend class="text-gray-700 dark:text-gray-300">{{ $axis->name }}</legend>
                                    @foreach ($axis->optionValues as $value)
                                        <label class="mr-4 inline-flex items-center gap-1">
                                            <input type="checkbox" name="option_value_id[]" value="{{ $value->id }}" @checked(in_array($value->id, $scoped, true)) class="rounded border-gray-400 dark:border-gray-600">
                                            {{ $value->label }}
                                        </label>
                                    @endforeach
                                </fieldset>
                            @endforeach

                            @if ($unaffectedLabel !== null)
                                <p class="mt-2 text-gray-600 dark:text-gray-400">
                                    Buyers who pick {{ $unaffectedLabel }} never see this question &mdash; no note in the description telling them to ignore a box.
                                </p>
                            @endif

                            <button type="submit" class="mt-2 rounded border border-gray-400 dark:border-gray-600 px-4 py-2">Save</button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('seller.listings.modifiers.destroy', [$listing, $modifier]) }}" class="mt-3">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-gray-700 dark:text-gray-300 underline">Remove</button>
                    </form>
                </div>
            @endforeach

            <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <p class="font-semibold text-gray-700 dark:text-gray-300">Ask another question</p>

                @if ($addKind !== null)
                    <form method="POST" action="{{ route('seller.listings.modifiers.store', $listing) }}" class="mt-3 flex flex-col gap-3">
                        @csrf
                        <input type="hidden" name="kind" value="{{ $addKind->value }}">
                        <input type="hidden" name="position" value="{{ $nextModifierPosition }}">

                        <p class="text-gray-600 dark:text-gray-400">{{ ModifierKindWord::forKind($addKind) }}</p>

                        <x-form.field name="prompt" label="The question" required maxlength="255" />
                        <x-form.field name="instructions" label="A note under it" hint="Optional." />

                        @if ($addKind === ModifierKind::Text)
                            <div class="flex flex-wrap items-end gap-6">
                                <x-form.field name="char_limit" label="Longest answer" type="number" step="1" min="1" hint="Letters — buyers see the limit before they type." />
                                <x-form.field name="add_on_price" label="Extra charge for this" value="0.00" hint="Buyers who don't see this question never pay it." />
                            </div>
                            <label class="flex items-center gap-2">
                                <input name="required" type="checkbox" value="1" class="rounded border-gray-400 dark:border-gray-600">
                                <span class="text-gray-700 dark:text-gray-300">Buyers must answer before they can buy</span>
                            </label>
                        @elseif ($addKind === ModifierKind::Measurement)
                            <div class="flex flex-wrap items-end gap-4">
                                <x-form.field name="unit" label="Unit" hint="Inches, cm, mm…" />
                                <x-form.field name="min_value" label="Smallest allowed" type="number" step="any" />
                                <x-form.field name="max_value" label="Largest allowed" type="number" step="any" hint="Within limits you set." />
                                <x-form.field name="rate" label="$ per unit" />
                            </div>
                            <label class="flex items-center gap-2">
                                <input name="required" type="checkbox" value="1" class="rounded border-gray-400 dark:border-gray-600">
                                <span class="text-gray-700 dark:text-gray-300">Buyers must answer before they can buy</span>
                            </label>
                        @else
                            <label class="flex items-center gap-2">
                                <input name="required" type="checkbox" value="1" class="rounded border-gray-400 dark:border-gray-600">
                                <span class="text-gray-700 dark:text-gray-300">Buyers must answer before they can buy</span>
                            </label>
                            <p class="text-gray-600 dark:text-gray-400">Add the options for buyers to pick from once this question is saved.</p>
                        @endif

                        <div class="flex items-center gap-3">
                            <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Add the question</button>
                            <a href="{{ route('seller.listings.modifiers.index', $listing) }}" class="text-gray-700 dark:text-gray-300 underline">Choose a different type</a>
                        </div>
                    </form>
                @else
                    <div class="mt-3 flex flex-wrap gap-3">
                        <a href="{{ route('seller.listings.modifiers.index', $listing) }}?kind=text" class="flex-1 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-3">
                            <p class="font-medium text-gray-900 dark:text-gray-100">They type it</p>
                            <p class="mt-1 text-gray-600 dark:text-gray-400">A name, a phrase, a date &mdash; with a length limit you set.</p>
                        </a>
                        <a href="{{ route('seller.listings.modifiers.index', $listing) }}?kind=select" class="flex-1 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-3">
                            <p class="font-medium text-gray-900 dark:text-gray-100">They pick from your list</p>
                            <p class="mt-1 text-gray-600 dark:text-gray-400">Fonts, thread colors, paper stocks &mdash; each option can carry its own price.</p>
                        </a>
                        <a href="{{ route('seller.listings.modifiers.index', $listing) }}?kind=measurement" class="flex-1 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-3">
                            <p class="font-medium text-gray-900 dark:text-gray-100">They give a measurement</p>
                            <p class="mt-1 text-gray-600 dark:text-gray-400">A waist in inches, a length in cm &mdash; the price can scale with the number ($ per inch), within limits you set.</p>
                        </a>
                        <div class="flex-1 rounded border border-dashed border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-3">
                            <p class="font-medium text-gray-500 dark:text-gray-500">
                                They attach a photo
                                <x-seller.coming-pill text="not yet" />
                            </p>
                            <p class="mt-1 text-gray-500 dark:text-gray-500">Until this ships, ask buyers to send reference photos through Messages after ordering.</p>
                        </div>
                    </div>
                @endif
            </div>

            <p class="text-gray-600 dark:text-gray-400">Gift wrap or rush turnaround? Keep them as their own listings for now &mdash; add-on checkboxes on this listing aren't available yet.</p>

    <x-slot:preview>
        @if ($preview === null)
            <x-seller.buyer-view :listing="$listing" />
        @else
            {{-- Pinned to the modifier's stored scope, never the request
                 (ScopedListingPreview::resolve reads only stored data) —
                 a live form here would accept a seller's clicks and then
                 silently discard them, so this pair renders disabled
                 rather than falsely interactive (IMPRV-015). --}}
            <x-seller.buyer-view :listing="$listing" :input="$preview->appliesInput" :caption="$preview->appliesCaption" :interactive="false" />
            <div>
                <x-seller.buyer-view :listing="$listing" :input="$preview->otherInput" :caption="$preview->otherCaption" :interactive="false" />
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-500">The question isn't greyed out &mdash; it simply isn't there. Nothing to ignore, nothing to explain in the description.</p>
            </div>
        @endif
    </x-slot:preview>
</x-layouts.seller-focused>
