<x-layouts.admin title="Payouts — Art Store admin">
    <h1 class="text-xl font-semibold">Payouts</h1>

    <x-admin.filters :action="route('admin.payouts.index')">
        <x-admin.seller-filter :sellers="$sellers" :selected="$sellerId" />
    </x-admin.filters>

    <form method="POST" action="{{ route('admin.payouts.run') }}" class="mt-4 flex flex-wrap items-end gap-3 rounded border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4">
        @csrf
        <div>
            <label for="as-of" class="block font-medium text-stone-700 dark:text-stone-300">Settle as of</label>
            <input id="as-of" name="as_of" type="date" value="{{ old('as_of') }}"
                   class="mt-1 rounded border border-stone-400 dark:border-stone-600 px-3 py-2">
            @error('as_of')
                <p class="mt-1 text-red-700 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
        <button type="submit" class="block w-full rounded bg-stone-700 hover:bg-stone-600 px-4 py-2 text-center font-medium text-white sm:inline-block sm:w-auto">Run weekly payout</button>
        <span class="text-stone-600 dark:text-stone-400">Settles every seller's released escrow for the week ending before this date, or today when left blank.</span>
    </form>

    @if ($payouts->isNotEmpty())
        @php
            // Folds the already-loaded $payouts (every seller's payout for
            // the current filter) into a per-week total — no query beyond
            // what the controller already ran.
            $chartPeriods = $payouts
                ->groupBy(fn ($payout) => $payout->period_start->format('Y-m-d'))
                ->map(fn ($group) => (object) [
                    'start' => $group->first()->period_start,
                    'totalCents' => (int) $group->sum('amount_cents'),
                ])
                ->sortBy('start')
                ->values()
                ->slice(-8);
            $maxPeriodCents = $chartPeriods->max('totalCents') ?: 1;
            $peakIndex = $chartPeriods->search(fn ($period) => $period->totalCents === $maxPeriodCents);
        @endphp

        <section aria-labelledby="paid-out-heading" class="mt-8">
            <h2 id="paid-out-heading" class="text-sm/6 font-semibold text-stone-900 dark:text-white">Paid out per week</h2>

            <div class="mt-2 rounded-lg border border-stone-200 px-6 pt-5 pb-3 dark:border-white/10">
                <div class="flex h-40 items-end gap-1 border-b border-stone-200 dark:border-white/10">
                    @foreach ($chartPeriods as $period)
                        <div class="flex h-full flex-1 flex-col items-center justify-end gap-1">
                            @if ($loop->index === $peakIndex)
                                <span class="text-xs font-medium text-stone-700 dark:text-stone-300">{{ \App\Domain\Money\Money::fromCents($period->totalCents)->format() }}</span>
                            @endif
                            <div
                                class="w-3/5 max-w-10 rounded-t bg-stone-600 dark:bg-stone-500"
                                style="height: {{ max(6, (int) round($period->totalCents / $maxPeriodCents * 100)) }}%"
                            ></div>
                        </div>
                    @endforeach
                </div>
                <div class="flex gap-1 pt-1.5">
                    @foreach ($chartPeriods as $period)
                        <span class="flex-1 text-center text-xs text-stone-500 dark:text-stone-400">{{ $period->start->format('M j') }}</span>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <x-admin.payouts-table :payouts="$payouts" caption="Every weekly payout, newest period first" />
</x-layouts.admin>
