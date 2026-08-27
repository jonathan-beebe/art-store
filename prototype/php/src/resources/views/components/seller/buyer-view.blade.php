@php
    use App\Domain\Configurator\ModifierKind;
    use App\Domain\Configurator\PricingMode;
    use App\Support\Configurator\PriceDifferenceInput;
@endphp

<div class="relative rounded-lg border border-dashed border-neutral-400 bg-white p-5 text-neutral-900">
    <span class="absolute -top-3 left-4 rounded-full bg-neutral-800 px-3 py-0.5 text-xs font-medium text-white">What buyers see @if ($caption !== null)— {{ $caption }}@endif</span>

    @if (! $hasConfigurator)
        <p class="text-sm text-neutral-500">Nothing here yet for a buyer to configure — this listing adds straight to cart.</p>
    @else
        @foreach ($configuration->axes as $axis)
            <div class="mt-4 first:mt-1">
                <label class="block text-sm font-medium text-neutral-700">{{ $axis['name'] }}</label>
                <select disabled aria-disabled="true"
                        class="mt-1 block w-full rounded-lg border border-neutral-300 bg-neutral-50 px-3 py-2 text-sm text-neutral-900">
                    @foreach ($axis['options'] as $option)
                        <option @selected($option['selected']) @disabled(! $option['selectable'])>
                            @if ($axis['pricingMode'] === PricingMode::Standalone)
                                {{ $option['label'] }}@unless($option['selected']) ({{ $option['price']->format() }})@endunless
                            @else
                                {{ $option['label'] }}@if (! $option['delta']->isZero()) ({{ $option['delta']->cents > 0 ? '+' : '' }}{{ $option['delta']->format() }})@endif
                            @endif
                            @if (! $option['selectable']) — {{ $option['reason'] }}@endif
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
                                @if ($unit['specLines'] !== [])
                                    <span class="mt-1 block text-neutral-500">
                                        @foreach ($unit['specLines'] as $line)
                                            {{ $line }}@if (! $loop->last), @endif
                                        @endforeach
                                    </span>
                                @endif
                                <span class="mt-1 block">{{ $unit['price']->format() }}</span>
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-3 text-xs text-neutral-500">The piece a buyer picks is exactly the one they get — its condition and measurements sit on the card, not scattered between a dropdown number, a photo caption, and a line in the description. Sold pieces don't appear at all.</p>
                @endif
            </div>
        @endif

        @foreach ($configuration->modifiers as $modifier)
            <div class="mt-4">
                <label class="block text-sm font-medium text-neutral-700">
                    {{ $modifier['prompt'] }}@if ($modifier['kind'] === ModifierKind::Text && $modifier['addOnPriceCents'] !== 0) <span class="font-normal text-neutral-500">({{ PriceDifferenceInput::format($modifier['addOnPriceCents']) }})</span>@endif @if ($modifier['required']) <span aria-hidden="true">*</span>@endif
                </label>

                @if ($modifier['instructions'] !== null)
                    <p class="mt-1 text-xs text-neutral-500">{{ $modifier['instructions'] }}</p>
                @endif

                @if ($modifier['kind'] === ModifierKind::Select)
                    <select disabled aria-disabled="true" class="mt-1 block w-full rounded-lg border border-neutral-300 bg-neutral-50 px-3 py-2 text-sm text-neutral-900">
                        @foreach ($modifier['options'] as $option)
                            <option @selected($option['selected'])>
                                {{ $option['label'] }}@if (! $option['delta']->isZero()) ({{ $option['delta']->cents > 0 ? '+' : '' }}{{ $option['delta']->format() }})@endif
                            </option>
                        @endforeach
                    </select>
                @elseif ($modifier['kind'] === ModifierKind::Measurement)
                    <input type="text" disabled aria-disabled="true" value="{{ $modifier['answer'] }}"
                           @if ($modifier['minValue'] !== null) min="{{ $modifier['minValue'] }}" @endif
                           @if ($modifier['maxValue'] !== null) max="{{ $modifier['maxValue'] }}" @endif
                           class="mt-1 block w-full rounded-lg border border-neutral-300 bg-neutral-50 px-3 py-2 text-sm text-neutral-900">
                    @if ($modifier['unit'] !== null)
                        <span class="mt-1 block text-xs text-neutral-500">{{ $modifier['unit'] }}</span>
                    @endif
                @else
                    <input type="text" disabled aria-disabled="true" value="{{ $modifier['answer'] }}"
                           @if ($modifier['charLimit'] !== null) maxlength="{{ $modifier['charLimit'] }}" @endif
                           class="mt-1 block w-full rounded-lg border border-neutral-300 bg-neutral-50 px-3 py-2 text-sm text-neutral-900">
                    @if ($modifier['charLimit'] !== null)
                        <span class="mt-1 block text-xs text-neutral-500">Up to {{ $modifier['charLimit'] }} letters.</span>
                    @endif
                @endif
            </div>
        @endforeach

        @if ($configuration->quantityTiers !== [])
            <div class="mt-4">
                <p class="text-sm font-medium text-neutral-700">Quantity discounts</p>
                <ul class="mt-1 text-sm text-neutral-600">
                    @foreach ($configuration->quantityTiers as $tier)
                        <li @class(['font-semibold text-neutral-900' => $tier['active']])>{{ $tier['minQty'] }}+: {{ rtrim(rtrim(number_format($tier['discountPercent'], 2), '0'), '.') }}% off</li>
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
                        <dd>{{ ! $loop->first && $line->amount->cents >= 0 ? '+' : '' }}{{ $line->amount->format() }}</dd>
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
