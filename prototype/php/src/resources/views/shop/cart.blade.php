<x-layouts.shop title="Cart — Art Store">
    <h1 class="text-4xl font-semibold tracking-tight">Cart</h1>

    @if ($cart->items->isEmpty())
        <p class="mt-10 text-lg text-neutral-600">Your cart is empty.</p>

        <a href="{{ route('shop.home') }}" class="mt-8 inline-block rounded-full bg-neutral-900 px-8 py-3 text-base font-medium text-white">
            Browse the art
        </a>
    @else
        <ul class="mt-12 divide-y divide-neutral-100 border-y border-neutral-100">
            @foreach ($cart->items as $item)
                <li class="flex flex-wrap items-center gap-6 py-6">
                    <img src="{{ $item->listing->imageUrl() }}" alt="{{ $item->listing->title }}"
                         class="aspect-square w-24 rounded-xl object-cover">

                    <div class="flex-1">
                        <a href="{{ route('shop.listing', $item->listing) }}" class="text-lg font-medium">{{ $item->listing->title }}</a>
                        <p class="mt-1 text-sm text-neutral-500">{{ $item->listing->seller->displayName() }}</p>

                        @if ($item->isConfigured())
                            <dl class="mt-1 text-sm text-neutral-500">
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
                                <p class="mt-2 inline-block rounded-full bg-red-50 px-3 py-1 text-sm font-medium text-red-900">
                                    {{ ucfirst($item->currentAvailability()->reason ?? 'unavailable') }}
                                </p>
                            @endunless
                        @endif

                        <p class="mt-1 text-sm text-neutral-500">Quantity {{ $item->quantity }}</p>

                        @if ($reason = $plan->blockedReasonFor($item->listing_id))
                            <p class="mt-2 inline-block rounded-full bg-red-50 px-3 py-1 text-sm font-medium text-red-900">
                                {{ ucfirst($reason->notice()) }}
                            </p>
                        @endif
                    </div>

                    <p class="text-lg">{{ $item->toLine()->total()->format() }}</p>

                    <form method="POST" action="{{ route('shop.cart.remove', $item->listing) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-neutral-500 underline hover:text-neutral-900">Remove</button>
                    </form>
                </li>
            @endforeach
        </ul>

        <div class="mt-10 flex flex-wrap items-center justify-between gap-6">
            <p class="text-xl">Subtotal <span class="ml-4 font-semibold">{{ $totals->subtotal->format() }}</span></p>

            @if ($plan->isPlaceable())
                <a href="{{ route('shop.checkout') }}" class="rounded-full bg-neutral-900 px-8 py-3 text-base font-medium text-white">
                    Checkout
                </a>
            @else
                <button type="button" disabled aria-disabled="true"
                        title="Remove what is no longer available before checking out"
                        class="cursor-not-allowed rounded-full bg-neutral-300 px-8 py-3 text-base font-medium text-neutral-500">
                    Checkout
                </button>
            @endif
        </div>
    @endif
</x-layouts.shop>
