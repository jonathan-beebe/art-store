@php
    use App\Domain\Configurator\UnitSpecLabel;
@endphp

<x-layouts.seller :title="'Units — '.$listing->title.' — Art Store seller'">
    <div class="flex flex-wrap items-center gap-4">
        <h1 class="text-xl font-semibold">Units</h1>
        <a href="{{ route('seller.listings.variants.index', $listing) }}" class="ml-auto text-gray-700 dark:text-gray-300 underline">Back to variants</a>
    </div>

    <p class="mt-2 text-gray-600 dark:text-gray-400">This variant's available quantity is derived from its units in state "available" — {{ $variant->availableUnitCount() }} right now.</p>

    @if ($units->isEmpty())
        <p class="mt-4 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 text-gray-600 dark:text-gray-400">No units yet.</p>
    @else
        <ul class="mt-4 space-y-3">
            @foreach ($units as $unit)
                <li class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                    <form method="POST" action="{{ route('seller.listings.variants.units.update', [$listing, $variant, $unit]) }}" class="flex flex-wrap items-end gap-3">
                        @csrf
                        @method('PUT')

                        <x-form.field name="label" label="Label" required maxlength="255" :value="$unit->label" />

                        <div>
                            <label for="state-{{ $unit->id }}" class="block font-medium text-gray-700 dark:text-gray-300">State</label>
                            <select id="state-{{ $unit->id }}" name="state" class="mt-1 block w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                                @foreach (\App\Domain\Configurator\UnitState::cases() as $state)
                                    <option value="{{ $state->value }}" @selected($unit->state === $state)>{{ ucfirst($state->value) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <x-form.field name="condition_note" label="Condition note" :value="$unit->condition_note" />

                        @if ($unit->specs_json !== null)
                            <p class="w-full text-sm text-gray-600 dark:text-gray-400">
                                @foreach ($unit->specs_json as $key => $value)
                                    {{ UnitSpecLabel::format($key, $value) }}@if (! $loop->last), @endif
                                @endforeach
                            </p>
                        @endif

                        <x-form.field name="specs" label="Specs (JSON)" :value="$unit->specs_json === null ? null : json_encode($unit->specs_json)" />
                        <x-form.field name="price_override" label="Price override (dollars)" :value="$unit->price_override_cents === null ? null : number_format($unit->price_override_cents / 100, 2, '.', '')" />

                        <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Save</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif

    <h2 class="mt-6 font-semibold text-gray-700 dark:text-gray-300">Add a unit</h2>

    <form method="POST" action="{{ route('seller.listings.variants.units.store', [$listing, $variant]) }}" class="mt-2 flex flex-wrap items-end gap-3 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
        @csrf

        <x-form.field name="label" label="Label" required maxlength="255" hint="A number or name unique to this variant, like #12." />
        <x-form.field name="condition_note" label="Condition note" />
        <x-form.field name="specs" label="Specs (JSON)" hint='Optional, e.g. {"height_mm": 240}.' />
        <x-form.field name="price_override" label="Price override (dollars)" />

        <button type="submit" class="rounded border border-gray-400 dark:border-gray-600 px-4 py-2">Add unit</button>
    </form>
</x-layouts.seller>
