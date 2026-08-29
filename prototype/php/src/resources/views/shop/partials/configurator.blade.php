@php
    use App\Domain\Configurator\ModifierKind;
    use App\Domain\Configurator\PricingMode;

    // IMPRV-015: one partial renders the configurator on `/art/{slug}` and
    // inside the seller's "What buyers see" panel — `$mode` is the only
    // thing that changes: `shop` posts to the cart, `preview` is a live GET
    // form that round-trips on the seller screen's own URL with an inert
    // Add to cart, `static` (the modifier scope demo's pinned pair) is a
    // disabled read-only rendering with no form at all. Every label,
    // option, price, and breakdown line below renders identically in all
    // three, so a rendering-rule change lands everywhere from this one file.
    $isForm = $mode !== 'static';
    $wrapperTag = $isForm ? 'form' : 'div';
    $wrapperAttributes = match ($mode) {
        'shop' => 'method="POST" action="'.e(route('shop.cart.add', $listing)).'" data-configurator',
        'preview' => 'method="GET" action="'.e($refreshUrl).'" data-configurator',
        default => '',
    };
@endphp

<{{ $wrapperTag }} {!! $wrapperAttributes !!} class="mt-2 max-w-lg">
    @if ($mode === 'shop')
        @csrf
    @endif

    @if ($mode === 'preview')
        @foreach (collect(request()->query())->except(['axis', 'unit', 'modifier', 'quantity', 'focus'])->all() as $preservedKey => $preservedValue)
            @if (is_scalar($preservedValue))
                <input type="hidden" name="{{ $preservedKey }}" value="{{ $preservedValue }}">
            @endif
        @endforeach
    @endif

    @if ($isForm)
        <input type="hidden" name="focus" data-configurator-focus>
    @endif

    @foreach ($configuration->axes as $axis)
        <div class="mt-6">
            <label for="axis-{{ $axis['id'] }}" class="block text-sm font-medium text-neutral-700">{{ $axis['name'] }}</label>
            <select id="axis-{{ $axis['id'] }}" name="axis[{{ $axis['id'] }}]" @if ($focusId === 'axis-'.$axis['id']) autofocus @endif
                    @if ($isForm) data-configurator-refresh @endif
                    @disabled(! $isForm) @if (! $isForm) aria-disabled="true" @endif
                    class="mt-2 block w-full rounded-xl border border-neutral-300 bg-white px-4 py-3 text-base focus:border-neutral-900 focus:outline-none">
                @foreach ($axis['options'] as $option)
                    <option value="{{ $option['id'] }}" @selected($option['selected']) @disabled(! $option['selectable'])>
                        @if ($axis['pricingMode'] === PricingMode::Standalone)
                            {{ $option['label'] }} ({{ $option['price']->format() }})
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
        <div class="mt-6">
            <span class="block text-sm font-medium text-neutral-700">Choose your piece</span>

            @if ($configuration->units === [])
                <p class="mt-2 text-red-700">Every piece has sold.</p>
            @else
                <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @foreach ($configuration->units as $unit)
                        <label class="block cursor-pointer rounded-2xl border p-4 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-offset-2 has-[:focus-visible]:ring-neutral-900 {{ $unit['selected'] ? 'border-neutral-900 ring-1 ring-neutral-900' : 'border-neutral-200' }}">
                            <input type="radio" id="unit-{{ $unit['id'] }}" name="unit" value="{{ $unit['id'] }}" @checked($unit['selected']) @if ($focusId === 'unit-'.$unit['id']) autofocus @endif
                                   @if ($isForm) data-configurator-refresh @endif
                                   @disabled(! $isForm) @if (! $isForm) aria-disabled="true" @endif
                                   class="sr-only">
                            <span class="block font-medium">{{ $unit['label'] }}</span>
                            @if ($unit['conditionNote'] !== null)
                                <span class="mt-1 block text-sm text-neutral-500">{{ $unit['conditionNote'] }}</span>
                            @endif
                            @if ($unit['specLines'] !== [])
                                <span class="mt-1 block text-sm text-neutral-500">
                                    @foreach ($unit['specLines'] as $line)
                                        {{ $line }}@if (! $loop->last), @endif
                                    @endforeach
                                </span>
                            @endif
                            <span class="mt-2 block text-base">{{ $unit['price']->format() }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    @foreach ($configuration->modifiers as $modifier)
        <div class="mt-6">
            <label for="modifier-{{ $modifier['id'] }}" class="block text-sm font-medium text-neutral-700">
                {{ $modifier['prompt'] }}@if ($modifier['required']) <span aria-hidden="true">*</span>@endif
            </label>

            @if ($modifier['instructions'] !== null)
                <p class="mt-1 text-sm text-neutral-500">{{ $modifier['instructions'] }}</p>
            @endif

            @if ($modifier['kind'] === ModifierKind::Select)
                <select id="modifier-{{ $modifier['id'] }}" name="modifier[{{ $modifier['id'] }}]"
                        @disabled(! $isForm) @if (! $isForm) aria-disabled="true" @endif
                        class="mt-2 block w-full rounded-xl border border-neutral-300 bg-white px-4 py-3 text-base focus:border-neutral-900 focus:outline-none">
                    @foreach ($modifier['options'] as $option)
                        <option value="{{ $option['id'] }}" @selected($option['selected'])>
                            {{ $option['label'] }}@if (! $option['delta']->isZero()) ({{ $option['delta']->cents > 0 ? '+' : '' }}{{ $option['delta']->format() }})@endif
                        </option>
                    @endforeach
                </select>
            @elseif ($modifier['kind'] === ModifierKind::Measurement)
                <input type="number" step="any" id="modifier-{{ $modifier['id'] }}" name="modifier[{{ $modifier['id'] }}]"
                       value="{{ $modifier['answer'] }}"
                       @if ($modifier['minValue'] !== null) min="{{ $modifier['minValue'] }}" @endif
                       @if ($modifier['maxValue'] !== null) max="{{ $modifier['maxValue'] }}" @endif
                       @required($modifier['required'] && $isForm)
                       @disabled(! $isForm) @if (! $isForm) aria-disabled="true" @endif
                       class="mt-2 block w-full rounded-xl border border-neutral-300 px-4 py-3 text-base focus:border-neutral-900 focus:outline-none">
                @if ($modifier['unit'] !== null)
                    <span class="mt-1 block text-sm text-neutral-500">{{ $modifier['unit'] }}</span>
                @endif
            @else
                <input type="text" id="modifier-{{ $modifier['id'] }}" name="modifier[{{ $modifier['id'] }}]"
                       value="{{ $modifier['answer'] }}"
                       @if ($modifier['charLimit'] !== null) maxlength="{{ $modifier['charLimit'] }}" @endif
                       @required($modifier['required'] && $isForm)
                       @disabled(! $isForm) @if (! $isForm) aria-disabled="true" @endif
                       class="mt-2 block w-full rounded-xl border border-neutral-300 px-4 py-3 text-base focus:border-neutral-900 focus:outline-none">
            @endif

            @if ($mode === 'shop')
                @error('modifier.'.$modifier['id'])
                    <p class="mt-2 text-red-700">{{ $message }}</p>
                @enderror
            @endif
        </div>
    @endforeach

    @if ($configuration->quantityTiers !== [])
        <div class="mt-6">
            <p class="text-sm font-medium text-neutral-700">Quantity discounts</p>
            <table class="mt-2 w-full text-sm">
                <tbody>
                    @foreach ($configuration->quantityTiers as $tier)
                        <tr class="{{ $tier['active'] ? 'font-semibold' : '' }}">
                            <td class="py-1">{{ $tier['minQty'] }}+</td>
                            <td class="py-1 text-right">{{ rtrim(rtrim(number_format($tier['discountPercent'], 2), '0'), '.') }}% off</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="mt-6">
        <label for="quantity" class="block text-sm font-medium text-neutral-700">Quantity</label>
        <input type="number" id="quantity" name="quantity" min="1" value="{{ $configuration->quantity }}"
               @if ($focusId === 'quantity') autofocus @endif
               @if ($isForm) data-configurator-refresh @endif
               @disabled(! $isForm) @if (! $isForm) aria-disabled="true" @endif
               class="mt-2 block w-32 rounded-xl border border-neutral-300 px-4 py-3 text-base focus:border-neutral-900 focus:outline-none">
    </div>

    <div class="mt-8 rounded-2xl border border-neutral-100 bg-neutral-50 p-6">
        <p class="text-sm font-medium text-neutral-700">Price</p>
        <dl class="mt-3 space-y-1 text-sm">
            @foreach ($configuration->breakdown->lines as $line)
                <div class="flex justify-between gap-4">
                    <dt class="text-neutral-600">{{ $line->label }}</dt>
                    <dd>{{ $line->signed && $line->amount->cents >= 0 ? '+' : '' }}{{ $line->amount->format() }}</dd>
                </div>
            @endforeach
        </dl>
        <div class="mt-3 flex justify-between gap-4 border-t border-neutral-200 pt-3 text-lg font-semibold">
            <span>Total</span>
            <span>{{ $configuration->breakdown->total()->format() }}</span>
        </div>
    </div>

    @if (! $configuration->canAddToCart)
        <p class="mt-4 inline-block rounded-full bg-red-50 px-4 py-2 text-sm font-medium text-red-900">
            {{ ucfirst($configuration->unavailableReason ?? 'unavailable') }}
        </p>
    @endif

    <div class="mt-6 flex flex-wrap items-center gap-4">
        @if ($isForm)
            <button type="submit" formmethod="GET" formaction="{{ $refreshUrl }}" formnovalidate
                    data-configurator-update
                    class="rounded-full border border-neutral-300 px-8 py-3 text-base font-medium hover:border-neutral-900">
                Update options
            </button>
        @else
            <span aria-disabled="true" class="rounded-full border border-neutral-300 px-6 py-2 text-sm font-medium text-neutral-400">Update options</span>
        @endif

        @include('shop.partials.add-to-cart-button', [
            'mode' => $mode,
            'listing' => $listing,
            'standalone' => false,
            'disabled' => ! $configuration->canAddToCart,
        ])
    </div>
</{{ $wrapperTag }}>
