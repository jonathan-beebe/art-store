@props(['orders', 'caption', 'showCustomer' => true])

@if ($orders->isEmpty())
    <x-admin.nothing>No orders.</x-admin.nothing>
@else
    <div class="mt-2 hidden overflow-x-auto rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 sm:block">
        <table class="w-full text-left">
            <caption class="sr-only">{{ $caption }}</caption>
            <thead class="border-b border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
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
            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
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

    <x-admin.card-list :caption="$caption">
        @foreach ($orders as $order)
            <x-admin.card-row>
                <a href="{{ route('admin.orders.show', $order) }}" class="font-medium underline">{{ $order->id }}</a>
                <div class="flex items-center justify-between gap-3 text-gray-600 dark:text-gray-400">
                    <span>{{ $order->status->label() }}</span>
                    <span class="tabular-nums text-gray-900 dark:text-gray-100">{{ $order->total()->format() }}</span>
                </div>
                <div class="text-gray-600 dark:text-gray-400">
                    @if ($showCustomer)
                        <a href="{{ route('admin.customers.show', $order->customer) }}" class="underline">{{ $order->customer->displayName() }}</a>
                        &middot;
                    @endif
                    {{ $order->items_count }} item{{ $order->items_count === 1 ? '' : 's' }} &middot; {{ $order->placed_at?->format('M j, Y') }}
                </div>
            </x-admin.card-row>
        @endforeach
    </x-admin.card-list>
@endif
