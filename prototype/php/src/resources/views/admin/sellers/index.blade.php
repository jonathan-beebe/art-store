<x-layouts.admin title="Sellers — Art Store admin">
    <h1 class="text-xl font-semibold">Sellers</h1>

    @if ($sellers->isEmpty())
        <x-admin.nothing class="mt-4">No sellers yet.</x-admin.nothing>
    @else
        <div class="mt-4 overflow-x-auto rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">
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
    @endif
</x-layouts.admin>
