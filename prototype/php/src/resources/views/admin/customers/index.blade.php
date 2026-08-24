<x-layouts.admin title="Customers — Art Store admin">
    <h1 class="text-xl font-semibold">Customers</h1>

    <x-admin.filters :action="route('admin.customers.index')">
        <x-admin.standing-filter :cases="$standings" :selected="$standing" />
    </x-admin.filters>

    @if ($customers->isEmpty())
        <x-admin.nothing class="mt-4">No customers match.</x-admin.nothing>
    @else
        <div class="mt-4 overflow-x-auto rounded border border-gray-300 bg-white">
            <table class="w-full text-left">
                <caption class="sr-only">Every customer on the platform, anonymous visitors included</caption>
                <thead class="border-b border-gray-300 bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-2 font-semibold">Customer</th>
                        <th scope="col" class="px-4 py-2 font-semibold">Email</th>
                        <th scope="col" class="px-4 py-2 font-semibold">Standing</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Orders</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Favorites</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Cart lines</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($customers as $customer)
                        <tr>
                            <th scope="row" class="px-4 py-2 font-normal">
                                <a href="{{ route('admin.customers.show', $customer) }}" class="font-medium underline">
                                    {{ $customer->displayName() }}
                                </a>
                            </th>
                            <td class="px-4 py-2">{{ $customer->email ?? '—' }}</td>
                            <td class="px-4 py-2">
                                @if ($customer->activeBlock)
                                    <span class="text-red-700">Blocked</span>
                                @elseif ($customer->isAnonymous())
                                    <span class="text-gray-600">Anonymous</span>
                                @elseif ($customer->isVerified())
                                    <span class="text-gray-600">Verified</span>
                                @else
                                    <span class="text-gray-600">Unverified</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $customer->orders_count }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $customer->favorites_count }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $customer->cart_items_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-layouts.admin>
