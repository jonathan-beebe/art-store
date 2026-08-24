@props(['orders', 'caption', 'showCustomer' => true])

@if ($orders->isEmpty())
    <x-admin.nothing>No orders.</x-admin.nothing>
@else
    <div class="mt-2 overflow-x-auto rounded border border-gray-300 bg-white">
        <table class="w-full text-left">
            <caption class="sr-only">{{ $caption }}</caption>
            <thead class="border-b border-gray-300 bg-gray-50">
                <tr>
                    <th scope="col" class="px-4 py-2 font-semibold">Order</th>
                    @if ($showCustomer)
                        <th scope="col" class="px-4 py-2 font-semibold">Customer</th>
                    @endif
                    <th scope="col" class="px-4 py-2 font-semibold">Status</th>
                    <th scope="col" class="px-4 py-2 text-right font-semibold">Items</th>
                    <th scope="col" class="px-4 py-2 text-right font-semibold">Total</th>
                    <th scope="col" class="px-4 py-2 font-semibold">Placed</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach ($orders as $order)
                    <tr>
                        <th scope="row" class="px-4 py-2 font-normal">
                            <a href="{{ route('admin.orders.show', $order) }}" class="font-medium underline">{{ $order->id }}</a>
                        </th>
                        @if ($showCustomer)
                            <td class="px-4 py-2">
                                <a href="{{ route('admin.customers.show', $order->customer) }}" class="underline">{{ $order->customer->displayName() }}</a>
                            </td>
                        @endif
                        <td class="px-4 py-2">{{ $order->status->label() }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $order->items_count }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $order->total()->format() }}</td>
                        <td class="px-4 py-2">{{ $order->placed_at?->format('M j, Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
