<x-layouts.admin title="Customers — Art Store admin" mode="list" empty-detail-prompt="Choose a customer to see their account.">
    <x-slot:cells>
        <div class="flex items-baseline gap-2 border-b border-gray-200 p-3 dark:border-gray-800">
            <h1 class="text-sm font-semibold">Customers</h1>
            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $customersTotal }}</span>
        </div>
        <div class="flex-1 overflow-y-auto">
            <x-admin.customers-cells :customers="$customers" />
        </div>
        <x-admin.cell-footer :shown="$customers->count()" :total="$customersTotal" :route="route('admin.customers.index')" />
    </x-slot:cells>

    <h1 class="text-xl font-semibold">Customers</h1>

    <x-admin.filters :action="route('admin.customers.index')">
        <x-admin.standing-filter :cases="$standings" :selected="$standing" />
    </x-admin.filters>

    @if ($customers->isEmpty())
        <x-admin.nothing class="mt-4">No customers match.</x-admin.nothing>
    @else
        <div class="mt-4 hidden overflow-x-auto rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 sm:block">
            <table class="w-full text-left">
                <caption class="sr-only">Every customer on the platform, anonymous visitors included</caption>
                <thead class="border-b border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th scope="col" class="px-4 py-2 font-semibold">Customer</th>
                        <th scope="col" class="px-4 py-2 font-semibold">Email</th>
                        <th scope="col" class="px-4 py-2 font-semibold">Standing</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Orders</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Favorites</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Cart lines</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
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
                                    <span class="text-red-700 dark:text-red-400">Blocked</span>
                                @elseif ($customer->isAnonymous())
                                    <span class="text-gray-600 dark:text-gray-400">Anonymous</span>
                                @elseif ($customer->isVerified())
                                    <span class="text-gray-600 dark:text-gray-400">Verified</span>
                                @else
                                    <span class="text-gray-600 dark:text-gray-400">Unverified</span>
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

        <x-admin.card-list class="mt-4" caption="Every customer on the platform, anonymous visitors included">
            @foreach ($customers as $customer)
                <x-admin.card-row>
                    <a href="{{ route('admin.customers.show', $customer) }}" class="font-medium underline">{{ $customer->displayName() }}</a>
                    <div class="flex items-center justify-between gap-3 text-gray-600 dark:text-gray-400">
                        @if ($customer->activeBlock)
                            <span class="text-red-700 dark:text-red-400">Blocked</span>
                        @elseif ($customer->isAnonymous())
                            <span>Anonymous</span>
                        @elseif ($customer->isVerified())
                            <span>Verified</span>
                        @else
                            <span>Unverified</span>
                        @endif
                        <span>{{ $customer->email ?? '—' }}</span>
                    </div>
                    <div class="text-gray-600 dark:text-gray-400">{{ $customer->orders_count }} order{{ $customer->orders_count === 1 ? '' : 's' }} &middot; {{ $customer->favorites_count }} favorite{{ $customer->favorites_count === 1 ? '' : 's' }} &middot; {{ $customer->cart_items_count }} cart line{{ $customer->cart_items_count === 1 ? '' : 's' }}</div>
                </x-admin.card-row>
            @endforeach
        </x-admin.card-list>
    @endif
</x-layouts.admin>
