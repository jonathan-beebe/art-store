<x-layouts.shop title="Checkout — Art Store">
    <h1 class="text-4xl font-semibold tracking-tight">Checkout</h1>

    @if (count($blocked) > 0)
        <div role="alert" class="mt-8 max-w-xl rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-900">
            <p>Take these out of your cart before placing the order:</p>
            <ul class="mt-2 list-disc pl-5">
                @foreach ($blocked as $line)
                    <li>{{ $line->title }} — {{ $line->reason->notice() }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mt-12 grid gap-16 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <form method="POST" action="{{ route('shop.checkout.place') }}" class="max-w-xl">
            @csrf

            <h2 class="text-sm font-medium uppercase tracking-wide text-neutral-500">Contact</h2>

            <div class="mt-4">
                <label for="email" class="block text-base font-medium">Email address</label>
                <input id="email" name="email" type="email" required autocomplete="email"
                       value="{{ old('email', $visitor->email) }}" @readonly($isVerified)
                       class="mt-2 block w-full rounded-lg border border-neutral-300 px-4 py-3 text-lg read-only:bg-neutral-50">
                @error('email')
                    <p class="mt-2 text-red-700">{{ $message }}</p>
                @enderror
            </div>

            @unless ($isVerified)
                <p class="mt-3 text-base text-neutral-600">
                    We email a link to this address. Opening it verifies you and takes you straight to payment.
                </p>
            @endunless

            <h2 class="mt-12 text-sm font-medium uppercase tracking-wide text-neutral-500">Shipping</h2>

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
                    <label for="{{ $field }}" class="block text-base font-medium">{{ $label[0] }}</label>
                    <input id="{{ $field }}" name="{{ $field }}" type="text" autocomplete="{{ $label[1] }}"
                           value="{{ old($field) }}" @required($field !== 'shipping_line2')
                           class="mt-2 block w-full rounded-lg border border-neutral-300 px-4 py-3 text-lg">
                    @error($field)
                        <p class="mt-2 text-red-700">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach

            @if ($isVerified)
                <h2 class="mt-12 text-sm font-medium uppercase tracking-wide text-neutral-500">Payment</h2>

                <x-card-fields />
            @endif

            <button type="submit" class="mt-10 rounded-full bg-neutral-900 px-8 py-3 text-base font-medium text-white">
                Place order
            </button>
        </form>

        <aside>
            <h2 class="text-sm font-medium uppercase tracking-wide text-neutral-500">Order</h2>

            <ul class="mt-4 divide-y divide-neutral-100 border-y border-neutral-100">
                @foreach ($cart->items as $item)
                    <li class="flex items-baseline justify-between gap-6 py-4">
                        <span class="text-base">{{ $item->listing->title }} × {{ $item->quantity }}</span>
                        <span class="text-base">{{ $item->toLine()->total()->format() }}</span>
                    </li>
                @endforeach
            </ul>

            <p class="mt-6 flex items-baseline justify-between text-lg">
                <span>Total</span>
                <span class="font-semibold">{{ $totals->subtotal->format() }}</span>
            </p>
        </aside>
    </div>
</x-layouts.shop>
