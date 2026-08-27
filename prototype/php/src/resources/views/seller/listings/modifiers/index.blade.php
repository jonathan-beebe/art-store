<x-layouts.seller :title="'Modifiers — '.$listing->title.' — Art Store seller'">
    <div class="flex flex-wrap items-center gap-4">
        <h1 class="text-xl font-semibold">Modifiers</h1>
        <a href="{{ route('seller.listings.edit', $listing) }}" class="ml-auto text-gray-700 dark:text-gray-300 underline">Back to listing</a>
    </div>

    <p class="mt-2 text-gray-600 dark:text-gray-400">A modifier is one order-line question — personalization text, a font choice, an engraved length. Scope it to show only for certain options, or leave it unscoped to always show.</p>

    @if ($modifiers->isEmpty())
        <p class="mt-4 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 text-gray-600 dark:text-gray-400">No modifiers yet.</p>
    @else
        <ul class="mt-4 space-y-6">
            @foreach ($modifiers as $modifier)
                <li class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                    <form method="POST" action="{{ route('seller.listings.modifiers.update', [$listing, $modifier]) }}" class="flex flex-wrap items-end gap-3">
                        @csrf
                        @method('PUT')

                        <div>
                            <label for="kind-{{ $modifier->id }}" class="block font-medium text-gray-700 dark:text-gray-300">Kind</label>
                            <select id="kind-{{ $modifier->id }}" name="kind" class="mt-1 block w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                                @foreach (\App\Domain\Configurator\ModifierKind::cases() as $kind)
                                    <option value="{{ $kind->value }}" @selected($modifier->kind === $kind)>{{ ucfirst($kind->value) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <x-form.field name="prompt" label="Prompt" required maxlength="255" :value="$modifier->prompt" />
                        <x-form.field name="instructions" label="Instructions" :value="$modifier->instructions" />
                        <x-form.field name="position" label="Position" type="number" step="1" min="0" required :value="$modifier->position" />

                        <div class="flex items-center gap-2">
                            <input id="required-{{ $modifier->id }}" name="required" type="checkbox" value="1" @checked($modifier->required) class="rounded border-gray-400 dark:border-gray-600">
                            <label for="required-{{ $modifier->id }}" class="text-gray-700 dark:text-gray-300">Required</label>
                        </div>

                        <x-form.field name="add_on_price" label="Add-on price (dollars)" :value="number_format($modifier->add_on_price_cents / 100, 2, '.', '')" hint="Flat add-on for text/measurement kinds." />
                        <x-form.field name="char_limit" label="Char limit (text)" type="number" step="1" min="1" :value="$modifier->char_limit" />
                        <x-form.field name="unit" label="Unit (measurement)" :value="$modifier->unit" />
                        <x-form.field name="min_value" label="Min value (measurement)" type="number" step="any" :value="$modifier->min_value" />
                        <x-form.field name="max_value" label="Max value (measurement)" type="number" step="any" :value="$modifier->max_value" />
                        <x-form.field name="rate" label="Rate per unit (dollars)" :value="$modifier->rate_cents_per_unit === null ? null : number_format($modifier->rate_cents_per_unit / 100, 2, '.', '')" />

                        <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Save</button>
                    </form>

                    <form method="POST" action="{{ route('seller.listings.modifiers.destroy', [$listing, $modifier]) }}" class="mt-2">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded border border-gray-400 dark:border-gray-600 px-3 py-1 text-sm">Remove modifier</button>
                    </form>

                    @if ($modifier->kind === \App\Domain\Configurator\ModifierKind::Select)
                        <h3 class="mt-4 font-semibold text-gray-700 dark:text-gray-300">Options</h3>

                        @if ($modifier->options->isEmpty())
                            <p class="mt-2 text-gray-600 dark:text-gray-400">No options yet.</p>
                        @else
                            <ul class="mt-2 space-y-2">
                                @foreach ($modifier->options as $option)
                                    <li class="rounded border border-gray-200 dark:border-gray-800 p-3">
                                        <form method="POST" action="{{ route('seller.listings.modifiers.options.update', [$listing, $modifier, $option]) }}" class="flex flex-wrap items-end gap-3">
                                            @csrf
                                            @method('PUT')

                                            <x-form.field name="label" label="Label" required maxlength="255" :value="$option->label" />
                                            <x-form.field name="add_on_price" label="Add-on price (dollars)" :value="number_format($option->add_on_price_cents / 100, 2, '.', '')" />
                                            <x-form.field name="position" label="Position" type="number" step="1" min="0" required :value="$option->position" />

                                            <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Save</button>
                                        </form>

                                        <form method="POST" action="{{ route('seller.listings.modifiers.options.destroy', [$listing, $modifier, $option]) }}" class="mt-2">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded border border-gray-400 dark:border-gray-600 px-3 py-1 text-sm">Remove option</button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <form method="POST" action="{{ route('seller.listings.modifiers.options.store', [$listing, $modifier]) }}" class="mt-4 flex flex-wrap items-end gap-3 border-t border-gray-200 dark:border-gray-800 pt-4">
                            @csrf

                            <x-form.field name="label" label="New option label" required maxlength="255" />
                            <x-form.field name="add_on_price" label="Add-on price (dollars)" value="0.00" />
                            <x-form.field name="position" label="Position" type="number" step="1" min="0" value="0" required />

                            <button type="submit" class="rounded border border-gray-400 dark:border-gray-600 px-4 py-2">Add option</button>
                        </form>
                    @endif

                    @if ($axes->isNotEmpty())
                        <h3 class="mt-4 font-semibold text-gray-700 dark:text-gray-300">Show this question only when…</h3>

                        <form method="POST" action="{{ route('seller.listings.modifiers.scope', [$listing, $modifier]) }}" class="mt-2">
                            @csrf

                            @php $scoped = $modifier->scopes->pluck('option_value_id')->all(); @endphp

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

                            <p class="mt-2 text-gray-600 dark:text-gray-400">Leave every box unchecked to show this question for every configuration.</p>

                            <button type="submit" class="mt-2 rounded border border-gray-400 dark:border-gray-600 px-4 py-2">Save scope</button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    <h2 class="mt-6 font-semibold text-gray-700 dark:text-gray-300">Add a modifier</h2>

    <form method="POST" action="{{ route('seller.listings.modifiers.store', $listing) }}" class="mt-2 flex flex-wrap items-end gap-3 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
        @csrf

        <div>
            <label for="new-kind" class="block font-medium text-gray-700 dark:text-gray-300">Kind</label>
            <select id="new-kind" name="kind" class="mt-1 block w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                @foreach (\App\Domain\Configurator\ModifierKind::cases() as $kind)
                    <option value="{{ $kind->value }}">{{ ucfirst($kind->value) }}</option>
                @endforeach
            </select>
        </div>

        <x-form.field name="prompt" label="Prompt" required maxlength="255" />
        <x-form.field name="instructions" label="Instructions" />
        <x-form.field name="position" label="Position" type="number" step="1" min="0" value="0" required />

        <div class="flex items-center gap-2">
            <input id="new-required" name="required" type="checkbox" value="1" class="rounded border-gray-400 dark:border-gray-600">
            <label for="new-required" class="text-gray-700 dark:text-gray-300">Required</label>
        </div>

        <x-form.field name="add_on_price" label="Add-on price (dollars)" value="0.00" />
        <x-form.field name="char_limit" label="Char limit (text)" type="number" step="1" min="1" />
        <x-form.field name="unit" label="Unit (measurement)" />
        <x-form.field name="min_value" label="Min value (measurement)" type="number" step="any" />
        <x-form.field name="max_value" label="Max value (measurement)" type="number" step="any" />
        <x-form.field name="rate" label="Rate per unit (dollars)" />

        <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Add modifier</button>
    </form>
</x-layouts.seller>
