@props(['fulfillments', 'caption', 'showSeller' => true, 'showOrder' => true])

@if ($fulfillments->isEmpty())
    <x-admin.nothing>No fulfillments.</x-admin.nothing>
@else
    <div class="mt-2 hidden overflow-x-auto rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 sm:block">
        <table class="w-full text-left">
            <caption class="sr-only">{{ $caption }}</caption>
            <thead class="border-b border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                <tr>
                    <th scope="col" class="px-4 py-2 font-semibold">Fulfillment</th>
                    @if ($showOrder)
                        <th scope="col" class="px-4 py-2 font-semibold">Order</th>
                    @endif
                    @if ($showSeller)
                        <th scope="col" class="px-4 py-2 font-semibold">Seller</th>
                    @endif
                    <th scope="col" class="px-4 py-2 font-semibold">Status</th>
                    <th scope="col" class="px-4 py-2 text-right font-semibold">Net</th>
                    <th scope="col" class="px-4 py-2 font-semibold">Shipped</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                @foreach ($fulfillments as $fulfillment)
                    <tr>
                        <th scope="row" class="px-4 py-2 font-normal">
                            <a href="{{ route('admin.fulfillments.show', $fulfillment) }}" class="font-medium underline">{{ $fulfillment->id }}</a>
                        </th>
                        @if ($showOrder)
                            <td class="px-4 py-2">
                                <a href="{{ route('admin.orders.show', $fulfillment->order) }}" class="underline">{{ $fulfillment->order->id }}</a>
                            </td>
                        @endif
                        @if ($showSeller)
                            <td class="px-4 py-2">
                                <a href="{{ route('admin.sellers.show', $fulfillment->seller) }}" class="underline">{{ $fulfillment->seller->displayName() }}</a>
                            </td>
                        @endif
                        <td class="px-4 py-2">{{ $fulfillment->status->label() }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $fulfillment->net()->format() }}</td>
                        <td class="px-4 py-2">{{ $fulfillment->shipped_at?->format('M j, Y') ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <x-admin.card-list :caption="$caption">
        @foreach ($fulfillments as $fulfillment)
            <x-admin.card-row>
                <a href="{{ route('admin.fulfillments.show', $fulfillment) }}" class="font-medium underline">{{ $fulfillment->id }}</a>
                <div class="flex items-center justify-between gap-3 text-gray-600 dark:text-gray-400">
                    <span>{{ $fulfillment->status->label() }}</span>
                    <span class="tabular-nums text-gray-900 dark:text-gray-100">{{ $fulfillment->net()->format() }}</span>
                </div>
                <div class="text-gray-600 dark:text-gray-400">
                    @if ($showOrder)
                        <a href="{{ route('admin.orders.show', $fulfillment->order) }}" class="underline">{{ $fulfillment->order->id }}</a>
                        &middot;
                    @endif
                    @if ($showSeller)
                        <a href="{{ route('admin.sellers.show', $fulfillment->seller) }}" class="underline">{{ $fulfillment->seller->displayName() }}</a>
                        &middot;
                    @endif
                    Shipped {{ $fulfillment->shipped_at?->format('M j, Y') ?? '—' }}
                </div>
            </x-admin.card-row>
        @endforeach
    </x-admin.card-list>
@endif
