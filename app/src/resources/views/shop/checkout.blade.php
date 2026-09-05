<x-layouts.shop title="Checkout — Art Store">
    <h1 class="font-display text-4xl leading-tight text-ink">Checkout</h1>

    @if (count($blocked) > 0)
        <x-ui.alert tone="danger" class="mt-8 max-w-xl">
            <p>Take these out of your cart before placing the order:</p>
            <ul class="mt-2 list-disc pl-5">
                @foreach ($blocked as $line)
                    <li>{{ $line->title }} — {{ $line->reason->notice() }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <div class="mt-12 grid gap-16 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <form method="POST" action="{{ route('shop.checkout.place') }}" class="max-w-xl">
            @csrf

            <h2 class="text-sm font-medium uppercase tracking-wide text-ink-faint">Contact</h2>

            <div class="mt-4">
                <x-ui.label for="email">Email address</x-ui.label>
                <input id="email" name="email" type="email" required autocomplete="email"
                       value="{{ old('email', $visitor->email) }}" @readonly($isVerified)
                       class="mt-2 block w-full rounded-field border border-line-strong bg-surface px-4 py-3 text-lg text-ink read-only:bg-canvas">
                @error('email')
                    <p class="mt-2 text-danger">{{ $message }}</p>
                @enderror
            </div>

            @unless ($isVerified)
                <p class="mt-3 text-base text-ink-muted">
                    We email a link to this address. Opening it verifies you and takes you straight to payment.
                </p>
            @endunless

            <h2 class="mt-12 text-sm font-medium uppercase tracking-wide text-ink-faint">Shipping</h2>

            @foreach ([
                'shipping_name' => ['Full name', 'name'],
                'shipping_line1' => ['Address', 'address-line1'],
                'shipping_line2' => ['Address line 2', 'address-line2'],
                'shipping_city' => ['City', 'address-level2'],
                'shipping_region' => ['Region', 'address-level1'],
                'shipping_postal_code' => ['Postal code', 'postal-code'],
                'shipping_country' => ['Country', 'country-name'],
            ] as $field => $label)
                <div class="mt-4">
                    <x-ui.label for="{{ $field }}">{{ $label[0] }}</x-ui.label>
                    <input id="{{ $field }}" name="{{ $field }}" type="text" autocomplete="{{ $label[1] }}"
                           value="{{ old($field) }}" @required($field !== 'shipping_line2')
                           class="mt-2 block w-full rounded-field border border-line-strong bg-surface px-4 py-3 text-lg text-ink">
                    @error($field)
                        <p class="mt-2 text-danger">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach

            @if ($isVerified)
                <h2 class="mt-12 text-sm font-medium uppercase tracking-wide text-ink-faint">Payment</h2>

                <x-card-fields />
            @endif

            <x-ui.button variant="primary" class="mt-10">
                Place order
            </x-ui.button>
        </form>

        <aside>
            <h2 class="text-sm font-medium uppercase tracking-wide text-ink-faint">Order</h2>

            <ul class="mt-4 divide-y divide-line border-y border-line">
                @foreach ($cart->items as $item)
                    <li class="flex items-baseline justify-between gap-6 py-4">
                        <span class="text-base text-ink">{{ $item->listing->title }} × {{ $item->quantity }}</span>
                        <span class="text-base text-ink">{{ $item->toLine()->total()->format() }}</span>
                    </li>
                @endforeach
            </ul>

            <p class="mt-6 flex items-baseline justify-between text-lg text-ink">
                <span>Total</span>
                <span class="font-semibold">{{ $totals->subtotal->format() }}</span>
            </p>
        </aside>
    </div>
</x-layouts.shop>
