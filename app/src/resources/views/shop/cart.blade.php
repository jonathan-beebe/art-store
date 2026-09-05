<x-layouts.shop title="Cart — Art Store">
    <h1 class="font-display text-4xl leading-tight text-ink">Cart</h1>

    @if ($cart->items->isEmpty())
        <p class="mt-10 text-lg text-ink-muted">Your cart is empty.</p>

        <a href="{{ route('shop.home') }}" class="mt-8 inline-block rounded-full bg-accent px-8 py-3 text-base font-medium text-on-accent hover:bg-accent-strong">
            Browse the art
        </a>
    @else
        <ul class="mt-12 divide-y divide-line border-y border-line">
            @foreach ($cart->items as $item)
                <li class="flex flex-wrap items-center gap-6 py-6">
                    <img src="{{ $item->listing->imageUrl() }}" alt="{{ $item->listing->title }}"
                         class="aspect-square w-24 rounded-field object-cover">

                    <div class="flex-1">
                        <a href="{{ route('shop.listing', $item->listing) }}" class="text-lg font-medium text-ink">{{ $item->listing->title }}</a>
                        <p class="mt-1 text-sm text-ink-faint">{{ $item->listing->seller->displayName() }}</p>

                        @if ($item->hasVariant())
                            <dl class="mt-1 text-sm text-ink-faint">
                                @foreach ($item->configuration_json ?? [] as $pair)
                                    <div><span class="font-medium">{{ $pair['axisName'] }}:</span> {{ $pair['optionValueLabel'] }}</div>
                                @endforeach
                                @if ($item->unit)
                                    <div><span class="font-medium">Piece:</span> {{ $item->unit->label }}</div>
                                @endif
                                @foreach ($item->answers_json ?? [] as $answer)
                                    <div><span class="font-medium">{{ $answer['prompt'] }}:</span> {{ $answer['answer'] }}</div>
                                @endforeach
                            </dl>

                            @unless ($item->currentAvailability()->selectable)
                                <p class="mt-2 inline-block rounded-full bg-danger-surface px-3 py-1 text-sm font-medium text-danger">
                                    {{ ucfirst($item->currentAvailability()->reason ?? 'unavailable') }}
                                </p>
                            @endunless
                        @endif

                        <p class="mt-1 text-sm text-ink-faint">Quantity {{ $item->quantity }}</p>

                        @if ($reason = $plan->blockedReasonFor($item->id))
                            <p class="mt-2 inline-block rounded-full bg-danger-surface px-3 py-1 text-sm font-medium text-danger">
                                {{ ucfirst($reason->notice()) }}
                            </p>
                        @endif
                    </div>

                    <p class="text-lg text-ink">{{ $item->toLine()->total()->format() }}</p>

                    <form method="POST" action="{{ route('shop.cart.remove', $item) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-ink-faint underline hover:text-accent">Remove</button>
                    </form>
                </li>
            @endforeach
        </ul>

        <div class="mt-10 flex flex-wrap items-center justify-between gap-6">
            <p class="text-xl text-ink">Subtotal <span class="ml-4 font-semibold">{{ $totals->subtotal->format() }}</span></p>

            @if ($plan->isPlaceable())
                <a href="{{ route('shop.checkout') }}" class="rounded-full bg-accent px-8 py-3 text-base font-medium text-on-accent hover:bg-accent-strong">
                    Checkout
                </a>
            @else
                <button type="button" disabled aria-disabled="true"
                        title="Remove what is no longer available before checking out"
                        class="cursor-not-allowed rounded-full bg-line px-8 py-3 text-base font-medium text-ink-faint">
                    Checkout
                </button>
            @endif
        </div>
    @endif
</x-layouts.shop>
