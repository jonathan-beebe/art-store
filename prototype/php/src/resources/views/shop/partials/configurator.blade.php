@php
    use App\Domain\Configurator\ModifierKind;
@endphp

<form method="POST" action="{{ route('shop.cart.add', $listing) }}" class="mt-2 max-w-lg">
    @csrf

    @foreach ($configuration->axes as $axis)
        <div class="mt-6">
            <label for="axis-{{ $axis['id'] }}" class="block text-sm font-medium text-neutral-700">{{ $axis['name'] }}</label>
            <select id="axis-{{ $axis['id'] }}" name="axis[{{ $axis['id'] }}]"
                    class="mt-2 block w-full rounded-xl border border-neutral-300 bg-white px-4 py-3 text-base focus:border-neutral-900 focus:outline-none">
                @foreach ($axis['options'] as $option)
                    <option value="{{ $option['id'] }}" @selected($option['selected']) @disabled(! $option['selectable'])>
                        {{ $option['label'] }}@if (! $option['delta']->isZero()) ({{ $option['delta']->cents > 0 ? '+' : '' }}{{ $option['delta']->format() }})@endif
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
                            <input type="radio" name="unit" value="{{ $unit['id'] }}" @checked($unit['selected']) class="sr-only">
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
                       @required($modifier['required'])
                       class="mt-2 block w-full rounded-xl border border-neutral-300 px-4 py-3 text-base focus:border-neutral-900 focus:outline-none">
                @if ($modifier['unit'] !== null)
                    <span class="mt-1 block text-sm text-neutral-500">{{ $modifier['unit'] }}</span>
                @endif
            @else
                <input type="text" id="modifier-{{ $modifier['id'] }}" name="modifier[{{ $modifier['id'] }}]"
                       value="{{ $modifier['answer'] }}"
                       @if ($modifier['charLimit'] !== null) maxlength="{{ $modifier['charLimit'] }}" @endif
                       @required($modifier['required'])
                       class="mt-2 block w-full rounded-xl border border-neutral-300 px-4 py-3 text-base focus:border-neutral-900 focus:outline-none">
            @endif

            @error('modifier.'.$modifier['id'])
                <p class="mt-2 text-red-700">{{ $message }}</p>
            @enderror
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
               class="mt-2 block w-32 rounded-xl border border-neutral-300 px-4 py-3 text-base focus:border-neutral-900 focus:outline-none">
    </div>

    <div class="mt-8 rounded-2xl border border-neutral-100 bg-neutral-50 p-6">
        <p class="text-sm font-medium text-neutral-700">Price</p>
        <dl class="mt-3 space-y-1 text-sm">
            @foreach ($configuration->breakdown->lines as $line)
                <div class="flex justify-between gap-4">
                    <dt class="text-neutral-600">{{ $line->label }}</dt>
                    <dd>{{ ! $loop->first && $line->amount->cents > 0 ? '+' : '' }}{{ $line->amount->format() }}</dd>
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
        <button type="submit" formmethod="GET" formaction="{{ route('shop.listing', $listing) }}" formnovalidate
                class="rounded-full border border-neutral-300 px-8 py-3 text-base font-medium hover:border-neutral-900">
            Update options
        </button>

        <button type="submit" @disabled(! $configuration->canAddToCart)
                class="rounded-full bg-neutral-900 px-8 py-3 text-base font-medium text-white disabled:cursor-not-allowed disabled:bg-neutral-300">
            Add to cart
        </button>
    </div>
</form>
