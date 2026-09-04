<x-layouts.seller :title="'Shipping label '.$fulfillment->order_id.' — Art Store seller'">
    @php
        $order = $fulfillment->order;
        $addressLines = collect([
            $order->shipping_name,
            $order->shipping_line1,
            $order->shipping_line2,
            trim($order->shipping_city.', '.$order->shipping_region.' '.$order->shipping_postal_code),
            $order->shipping_country,
        ])->filter();
    @endphp

    <div class="print:hidden">
        <x-seller.back-link :route="route('seller.orders.show', $fulfillment->id)" label="Order" />

        <h1 class="text-xl font-semibold">Shipping label</h1>
        <p class="mt-1 text-sm/6 text-gray-500 dark:text-gray-400">
            Print this page and put it on the parcel. Mark the order shipped once it is with the carrier.
        </p>

        <div class="mt-4 flex items-center gap-3">
            <a href="{{ route('seller.orders.show', $fulfillment->id) }}" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/10 dark:hover:bg-white/20">Back to the order</a>
        </div>
    </div>

    <section aria-labelledby="label-heading" class="mt-6 max-w-xl rounded-lg border-2 border-gray-900 bg-white p-6 text-gray-900 print:mt-0 print:border-black">
        <h2 id="label-heading" class="text-xs font-semibold tracking-widest uppercase">Art Store</h2>

        <p class="mt-4 text-xs tracking-widest uppercase">Ship to</p>
        <address class="mt-1 text-base not-italic">
            @foreach ($addressLines as $line)
                <span class="block">{{ $line }}</span>
            @endforeach
        </address>

        <dl class="mt-6 grid grid-cols-2 gap-4 border-t-2 border-gray-900 pt-4 text-sm">
            <div>
                <dt class="text-xs tracking-widest uppercase">Carrier</dt>
                <dd class="mt-1 font-semibold">{{ $shipment?->carrier ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs tracking-widest uppercase">Tracking</dt>
                <dd class="mt-1 font-mono font-semibold">{{ $shipment?->tracking_number ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs tracking-widest uppercase">Order</dt>
                <dd class="mt-1 font-mono">{{ $fulfillment->order_id }}</dd>
            </div>
            <div>
                <dt class="text-xs tracking-widest uppercase">Parcel</dt>
                <dd class="mt-1 font-mono">{{ $fulfillment->id }}</dd>
            </div>
        </dl>
    </section>

    <p class="mt-4 max-w-xl text-xs/5 text-gray-500 print:hidden dark:text-gray-400">
        A carrier integration would answer a real PDF here.
    </p>
</x-layouts.seller>
