<x-layouts.seller title="Orders — Art Store seller">
    <h1 class="text-xl font-semibold">Orders</h1>

    @foreach ($groups as $group)
        <section aria-labelledby="group-{{ $group['status']->value }}" class="mt-6">
            <h2 id="group-{{ $group['status']->value }}" class="font-semibold text-gray-700">
                {{ $group['label'] }} ({{ $group['fulfillments']->count() }})
            </h2>

            @if ($group['fulfillments']->isEmpty())
                <p class="mt-2 rounded border border-gray-300 bg-white p-4 text-gray-600">Nothing here.</p>
            @else
                <div class="mt-2 overflow-x-auto rounded border border-gray-300 bg-white">
                    <table class="w-full text-left">
                        <caption class="sr-only">{{ $group['label'] }} orders</caption>
                        <thead class="border-b border-gray-300 bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-2 font-semibold">Order</th>
                                <th scope="col" class="px-4 py-2 font-semibold">Buyer</th>
                                <th scope="col" class="px-4 py-2 font-semibold">Items</th>
                                <th scope="col" class="px-4 py-2 text-right font-semibold">Net</th>
                                <th scope="col" class="px-4 py-2 font-semibold">Placed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach ($group['fulfillments'] as $fulfillment)
                                <tr>
                                    <th scope="row" class="px-4 py-2 font-normal">
                                        <a href="{{ route('seller.orders.show', $fulfillment->id) }}" class="font-medium underline">{{ $fulfillment->order_id }}</a>
                                    </th>
                                    <td class="px-4 py-2">{{ $fulfillment->order->shipping_name }}</td>
                                    <td class="px-4 py-2">{{ $fulfillment->order->items->pluck('title')->join(', ') }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums">{{ $fulfillment->net()->format() }}</td>
                                    <td class="px-4 py-2">{{ $fulfillment->order->placed_at?->format('M j, Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endforeach
</x-layouts.seller>
