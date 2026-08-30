<x-layouts.admin title="Sellers — Art Store admin" mode="list" empty-detail-prompt="Choose a seller to see their shop.">
    <x-slot:cells>
        <div class="flex items-baseline gap-2 border-b border-gray-200 p-3 dark:border-gray-800">
            <h1 class="text-sm font-semibold">Sellers</h1>
            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $sellers->count() }}</span>
        </div>
        <div class="flex-1 overflow-y-auto">
            <x-admin.sellers-cells :sellers="$sellers" :balances="$balances" />
        </div>
    </x-slot:cells>

    <h1 class="text-xl font-semibold">Sellers</h1>

    @if ($sellers->isEmpty())
        <x-admin.nothing class="mt-4">No sellers yet.</x-admin.nothing>
    @else
        <div class="mt-4 hidden overflow-x-auto rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 sm:block">
            <table class="w-full text-left">
                <caption class="sr-only">Every seller on the platform, with the balance folded from the ledger</caption>
                <thead class="border-b border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th scope="col" class="px-4 py-2 font-semibold">Shop</th>
                        <th scope="col" class="px-4 py-2 font-semibold">Email</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Listings</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Fulfillments</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Held</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Available</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Paid out</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @foreach ($sellers as $seller)
                        @php($balance = $balances->of($seller->id))
                        <tr>
                            <th scope="row" class="px-4 py-2 font-normal">
                                <a href="{{ route('admin.sellers.show', $seller) }}" class="font-medium underline">{{ $seller->displayName() }}</a>
                            </th>
                            <td class="px-4 py-2">{{ $seller->email }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $seller->listings_count }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $seller->fulfillments_count }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $balance->held->format() }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $balance->available->format() }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $balance->paidOut->format() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-admin.card-list class="mt-4" caption="Every seller on the platform, with the balance folded from the ledger">
            @foreach ($sellers as $seller)
                @php($balance = $balances->of($seller->id))
                <x-admin.card-row>
                    <a href="{{ route('admin.sellers.show', $seller) }}" class="font-medium underline">{{ $seller->displayName() }}</a>
                    <div class="text-gray-600 dark:text-gray-400">{{ $seller->email }}</div>
                    <div class="text-gray-600 dark:text-gray-400">{{ $seller->listings_count }} listing{{ $seller->listings_count === 1 ? '' : 's' }} &middot; {{ $seller->fulfillments_count }} fulfillment{{ $seller->fulfillments_count === 1 ? '' : 's' }}</div>
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 tabular-nums text-gray-900 dark:text-gray-100">
                        <span>Held {{ $balance->held->format() }}</span>
                        <span>Available {{ $balance->available->format() }}</span>
                        <span>Paid {{ $balance->paidOut->format() }}</span>
                    </div>
                </x-admin.card-row>
            @endforeach
        </x-admin.card-list>
    @endif
</x-layouts.admin>
