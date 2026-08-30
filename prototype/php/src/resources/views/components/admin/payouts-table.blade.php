@props(['payouts', 'caption', 'showSeller' => true])

@if ($payouts->isEmpty())
    <x-admin.nothing class="mt-4">No payouts yet.</x-admin.nothing>
@else
    <div class="mt-4 hidden overflow-x-auto rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 sm:block">
        <table class="w-full text-left">
            <caption class="sr-only">{{ $caption }}</caption>
            <thead class="border-b border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                <tr>
                    <th scope="col" class="px-4 py-2 font-semibold">Period</th>
                    @if ($showSeller)
                        <th scope="col" class="px-4 py-2 font-semibold">Seller</th>
                    @endif
                    <th scope="col" class="px-4 py-2 text-right font-semibold">Amount</th>
                    <th scope="col" class="px-4 py-2 font-semibold">Paid</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                @foreach ($payouts as $payout)
                    <tr>
                        <th scope="row" class="px-4 py-2 font-normal">
                            {{ $payout->period_start?->format('M j, Y') }} – {{ $payout->period_end?->format('M j, Y') }}
                        </th>
                        @if ($showSeller)
                            <td class="px-4 py-2">
                                <a href="{{ route('admin.sellers.show', $payout->seller) }}" class="underline">{{ $payout->seller->displayName() }}</a>
                            </td>
                        @endif
                        <td class="px-4 py-2 text-right tabular-nums">{{ $payout->amount()->format() }}</td>
                        <td class="px-4 py-2">{{ $payout->paid_at?->format('M j, Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <x-admin.card-list :caption="$caption" class="mt-4">
        @foreach ($payouts as $payout)
            <x-admin.card-row>
                <div class="flex items-center justify-between gap-3">
                    <span class="font-medium">{{ $payout->period_start?->format('M j, Y') }} – {{ $payout->period_end?->format('M j, Y') }}</span>
                    <span class="tabular-nums text-gray-900 dark:text-gray-100">{{ $payout->amount()->format() }}</span>
                </div>
                <div class="text-gray-600 dark:text-gray-400">
                    @if ($showSeller)
                        <a href="{{ route('admin.sellers.show', $payout->seller) }}" class="underline">{{ $payout->seller->displayName() }}</a>
                        &middot;
                    @endif
                    Paid {{ $payout->paid_at?->format('M j, Y') }}
                </div>
            </x-admin.card-row>
        @endforeach
    </x-admin.card-list>
@endif
