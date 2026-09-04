@props(['payouts', 'caption', 'showSeller' => true])

@if ($payouts->isEmpty())
    <x-admin.nothing class="mt-4">No payouts yet.</x-admin.nothing>
@else
    <div class="mt-4 hidden overflow-x-auto rounded border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 sm:block">
        <table class="w-full text-left">
            <caption class="sr-only">{{ $caption }}</caption>
            <thead class="border-b border-stone-300 dark:border-stone-700 bg-stone-50 dark:bg-stone-800/50">
                <tr>
                    <th scope="col" class="px-4 py-2 font-semibold">Period</th>
                    @if ($showSeller)
                        <th scope="col" class="px-4 py-2 font-semibold">Seller</th>
                    @endif
                    <th scope="col" class="px-4 py-2 text-right font-semibold">Amount</th>
                    <th scope="col" class="px-4 py-2 font-semibold">Paid</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200 dark:divide-stone-800">
                @foreach ($payouts as $payout)
                    <tr>
                        <th scope="row" class="px-4 py-2 text-left font-normal text-stone-500 dark:text-stone-400">
                            {{ $payout->period_start?->format('M j, Y') }} – {{ $payout->period_end?->format('M j, Y') }}
                        </th>
                        @if ($showSeller)
                            <td class="px-4 py-2 text-stone-500 dark:text-stone-400">
                                <a href="{{ route('admin.sellers.show', $payout->seller) }}" class="underline">{{ $payout->seller->displayName() }}</a>
                            </td>
                        @endif
                        <td class="px-4 py-2 text-right font-semibold tabular-nums text-stone-900 dark:text-white">{{ $payout->amount()->format() }}</td>
                        <td class="px-4 py-2 text-stone-500 dark:text-stone-400">{{ $payout->paid_at?->format('M j, Y') }}</td>
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
                    <span class="font-semibold tabular-nums text-stone-900 dark:text-white">{{ $payout->amount()->format() }}</span>
                </div>
                <div class="text-stone-600 dark:text-stone-400">
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
