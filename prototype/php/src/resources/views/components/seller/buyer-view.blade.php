@php
    use App\Domain\Configurator\ModifierKind;
@endphp

<div class="relative rounded-lg border border-dashed border-neutral-400 bg-white p-5 text-neutral-900">
    <span class="absolute -top-3 left-4 rounded-full bg-neutral-800 px-3 py-0.5 text-xs font-medium text-white">What buyers see</span>

    @if (! $hasConfigurator)
        <p class="text-sm text-neutral-500">Nothing here yet for a buyer to configure — this listing adds straight to cart.</p>
    @else
        @foreach ($configuration->axes as $axis)
            <div class="mt-4 first:mt-1">
                <label class="block text-sm font-medium text-neutral-700">{{ $axis['name'] }}</label>
                <select disabled aria-disabled="true"
                        class="mt-1 block w-full rounded-lg border border-neutral-300 bg-neutral-50 px-3 py-2 text-sm text-neutral-900">
                    @foreach ($axis['options'] as $option)
                        <option @selected($option['selected'])>
                            {{ $option['label'] }}@if (! $option['delta']->isZero()) ({{ $option['delta']->cents > 0 ? '+' : '' }}{{ $option['delta']->format() }})@endif
                        </option>
                    @endforeach
                </select>
            </div>
        @endforeach

        @if ($configuration->isSerialized)
            <div class="mt-4">
                <span class="block text-sm font-medium text-neutral-700">Choose your piece</span>

                @if ($configuration->units === [])
                    <p class="mt-2 text-sm text-red-700">Every piece has sold.</p>
                @else
                    <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                        @foreach ($configuration->units as $unit)
                            <div aria-disabled="true"
                                 class="rounded-lg border p-3 text-sm {{ $unit['selected'] ? 'border-neutral-900' : 'border-neutral-200' }}">
                                <span class="block font-medium">{{ $unit['label'] }}</span>
                                @if ($unit['conditionNote'] !== null)
                                    <span class="mt-1 block text-neutral-500">{{ $unit['conditionNote'] }}</span>
                                @endif
                                <span class="mt-1 block">{{ $unit['price']->format() }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        @foreach ($configuration->modifiers as $modifier)
            <div class="mt-4">
                <label class="block text-sm font-medium text-neutral-700">
                    {{ $modifier['prompt'] }}@if ($modifier['required']) <span aria-hidden="true">*</span>@endif
                </label>

                @if ($modifier['kind'] === ModifierKind::Select)
                    <select disabled aria-disabled="true" class="mt-1 block w-full rounded-lg border border-neutral-300 bg-neutral-50 px-3 py-2 text-sm text-neutral-900">
                        @foreach ($modifier['options'] as $option)
                            <option @selected($option['selected'])>
                                {{ $option['label'] }}@if (! $option['delta']->isZero()) ({{ $option['delta']->cents > 0 ? '+' : '' }}{{ $option['delta']->format() }})@endif
                            </option>
                        @endforeach
                    </select>
                @else
                    <input type="text" disabled aria-disabled="true" value="{{ $modifier['answer'] }}"
                           class="mt-1 block w-full rounded-lg border border-neutral-300 bg-neutral-50 px-3 py-2 text-sm text-neutral-900">
                @endif
            </div>
        @endforeach

        @if ($configuration->quantityTiers !== [])
            <div class="mt-4">
                <p class="text-sm font-medium text-neutral-700">Quantity discounts</p>
                <ul class="mt-1 text-sm text-neutral-600">
                    @foreach ($configuration->quantityTiers as $tier)
                        <li>{{ $tier['minQty'] }}+: {{ rtrim(rtrim(number_format($tier['discountPercent'], 2), '0'), '.') }}% off</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mt-5 rounded-xl border border-neutral-100 bg-neutral-50 p-4">
            <p class="text-sm font-medium text-neutral-700">Price</p>
            <dl class="mt-2 space-y-1 text-sm">
                @foreach ($configuration->breakdown->lines as $line)
                    <div class="flex justify-between gap-4">
                        <dt class="text-neutral-600">{{ $line->label }}</dt>
                        <dd>{{ ! $loop->first && $line->amount->cents > 0 ? '+' : '' }}{{ $line->amount->format() }}</dd>
                    </div>
                @endforeach
            </dl>
            <div class="mt-2 flex justify-between gap-4 border-t border-neutral-200 pt-2 text-base font-semibold">
                <span>Total</span>
                <span>{{ $configuration->breakdown->total()->format() }}</span>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3 text-sm">
            <span aria-disabled="true" class="rounded-full border border-neutral-300 px-6 py-2 font-medium text-neutral-400">Update options</span>
            <span aria-disabled="true" class="rounded-full bg-neutral-300 px-6 py-2 font-medium text-white">Add to cart</span>
        </div>
    @endif
</div>
