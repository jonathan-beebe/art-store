@php
    use App\Domain\Configurator\UnitState;
    use App\Support\Configurator\UnitStateWord;
@endphp

<x-layouts.seller :title="'Individual pieces — '.$listing->title.' — Art Store seller'">
    <p class="text-sm">
        <a href="{{ route('seller.listings.edit', $listing) }}" class="text-gray-700 dark:text-gray-300 underline">&larr; {{ $listing->title }}</a>
    </p>

    <h1 class="mt-2 text-xl font-semibold">Individual pieces</h1>
    @if ($comboLabel !== null)
        <p class="mt-1 text-gray-600 dark:text-gray-400">{{ $comboLabel }}</p>
    @endif
    <p class="mt-2 max-w-2xl text-gray-600 dark:text-gray-400">
        Each piece is its own thing &mdash; its own price, condition, and measurements, in one listing that keeps your reviews and search rank. The moment one sells it comes off the listing by itself; the rest stay up untouched.
    </p>

    <div class="mt-4 grid items-start gap-6 lg:grid-cols-[1fr_24rem]">
        <div class="flex flex-col gap-4">
            <p class="font-medium">
                {{ $counts['total'] }} pieces &middot; {{ $counts['available'] }} available &middot; {{ $counts['sold'] }} sold
                @if ($counts['onHold'] > 0)
                    &middot; {{ $counts['onHold'] }} on hold
                @endif
            </p>

            @if ($pieces->isEmpty())
                <p class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 text-gray-600 dark:text-gray-400">No pieces yet &mdash; add the first one below.</p>
            @else
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($pieces as $piece)
                        <div class="rounded border border-gray-300 dark:border-gray-700 p-4 {{ $piece['isSold'] ? 'bg-gray-50 dark:bg-gray-800/40' : 'bg-white dark:bg-gray-900' }}">
                            @if ($editingUnitId === $piece['id'])
                                <form method="POST" action="{{ route('seller.listings.variants.units.update', [$listing, $variant, $piece['unit']]) }}" class="flex flex-col gap-3">
                                    @csrf
                                    @method('PUT')

                                    <x-form.field name="label" label="Name or number" required maxlength="255" :value="$piece['unit']->label" />
                                    <x-form.field name="condition_note" label="Condition, in your words" :value="$piece['unit']->condition_note" />
                                    <x-form.field name="price_override" label="Price" :value="$piece['unit']->price_override_cents === null ? null : number_format($piece['unit']->price_override_cents / 100, 2, '.', '')" />

                                    <div>
                                        <span class="block font-medium text-gray-700 dark:text-gray-300">Measurements</span>
                                        <div class="mt-1 flex flex-col gap-2">
                                            @foreach ($piece['specRows'] as $i => $row)
                                                <div class="flex flex-wrap gap-2">
                                                    <input type="text" name="specs[{{ $i }}][label]" value="{{ old("specs.$i.label", $row['label']) }}" placeholder="Label, like Height" class="rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                                                    <input type="text" name="specs[{{ $i }}][value]" value="{{ old("specs.$i.value", $row['value']) }}" placeholder="Value, like 26 cm" class="rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div>
                                        <label for="state-{{ $piece['id'] }}" class="block font-medium text-gray-700 dark:text-gray-300">Mark as</label>
                                        <select id="state-{{ $piece['id'] }}" name="state" class="mt-1 block rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                                            @foreach (UnitState::cases() as $state)
                                                <option value="{{ $state->value }}" @selected($piece['unit']->state === $state)>{{ UnitStateWord::forState($state) }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Save</button>
                                        <a href="{{ route('seller.listings.variants.units.index', [$listing, $variant]) }}" class="text-gray-700 dark:text-gray-300 underline">Cancel</a>
                                    </div>
                                </form>
                            @else
                                <div class="flex flex-wrap items-baseline gap-2">
                                    <p class="font-semibold {{ $piece['isSold'] ? 'text-gray-500 dark:text-gray-500' : '' }}">{{ $piece['unit']->label }}</p>
                                    <span class="rounded-full border px-2 py-0.5 text-xs {{ $piece['isAvailable'] ? 'border-green-300 bg-green-50 text-green-900 dark:border-green-700 dark:bg-green-950 dark:text-green-300' : 'border-gray-300 bg-gray-100 text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400' }}">{{ $piece['stateWord'] }}</span>
                                    @if (! $piece['isSold'])
                                        <a href="{{ route('seller.listings.variants.units.index', [$listing, $variant]) }}?edit={{ $piece['id'] }}" class="ml-auto text-gray-700 dark:text-gray-300 underline">Edit</a>
                                    @endif
                                </div>

                                @if ($piece['specLines'] !== [])
                                    <p class="mt-2 {{ $piece['isSold'] ? 'text-gray-400 dark:text-gray-600' : '' }}">{{ implode(' · ', $piece['specLines']) }}</p>
                                @endif

                                @if ($piece['unit']->condition_note !== null)
                                    <p class="mt-1 {{ $piece['isSold'] ? 'text-gray-400 dark:text-gray-600' : 'text-gray-600 dark:text-gray-400' }}">{{ $piece['unit']->condition_note }}</p>
                                @endif

                                <p class="mt-2 font-semibold {{ $piece['isSold'] ? 'text-gray-400 dark:text-gray-600' : '' }}">{{ $piece['price']->format() }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <p class="font-semibold text-gray-700 dark:text-gray-300">Add a piece</p>

                <form method="POST" action="{{ route('seller.listings.variants.units.store', [$listing, $variant]) }}" class="mt-3 flex flex-col gap-3">
                    @csrf

                    <div class="flex flex-wrap items-end gap-3">
                        <x-form.field name="label" label="Name or number" required maxlength="255" hint="A number or name unique to this piece, like No. 53." />
                        <x-form.field name="condition_note" label="Condition, in your words" />
                        <x-form.field name="price_override" label="Price" hint="Optional — leave blank to use this combination's price." />
                    </div>

                    <div>
                        <span class="block font-medium text-gray-700 dark:text-gray-300">Measurements</span>
                        <p class="text-gray-600 dark:text-gray-400">More rows can be added once this piece is saved.</p>
                        <div class="mt-1 flex flex-col gap-2">
                            @for ($i = 0; $i < 3; $i++)
                                <div class="flex flex-wrap gap-2">
                                    <input type="text" name="specs[{{ $i }}][label]" value="{{ old("specs.$i.label") }}" placeholder="Label, like Height" class="rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                                    <input type="text" name="specs[{{ $i }}][value]" value="{{ old("specs.$i.value") }}" placeholder="Value, like 26 cm" class="rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                                </div>
                            @endfor
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex h-[72px] w-[72px] shrink-0 items-center justify-center rounded border border-dashed border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 text-center text-xs text-gray-400">photo</div>
                        <p class="max-w-sm text-gray-600 dark:text-gray-400">
                            Per-piece photos are <x-seller.coming-pill />. Until then, number your pieces in the listing photos the way you do today.
                        </p>
                        <button type="submit" class="ml-auto rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Add the piece</button>
                    </div>
                </form>
            </div>

            <p class="text-gray-600 dark:text-gray-400">Selling by weight or length &mdash; a 100 g bag of mixed beads at one price &mdash; isn't supported yet; say so in the listing page for now.</p>
        </div>

        <x-seller.buyer-view :listing="$listing" :input="$buyerViewInput" />
    </div>
</x-layouts.seller>
