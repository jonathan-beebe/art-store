<x-layouts.admin title="Dashboard — Art Store admin">
    <h1 class="text-xl font-semibold">Dashboard</h1>

    {{-- Below `sm`: the dashboard is a drill-down hub — every status count
         is a link into its filtered list, grouped in cards. At `sm` and
         up: today's static tally grids, unchanged. --}}
    <div class="mt-6 flex flex-col gap-4 sm:hidden">
        <div class="rounded border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900">
            <div class="border-b border-stone-200 dark:border-stone-800 px-4 py-2 text-sm font-semibold text-stone-700 dark:text-stone-300">Platform money</div>
            <div class="grid grid-cols-2 divide-x divide-y divide-stone-200 dark:divide-stone-800 [&>div:nth-child(1)]:border-t-0 [&>div:nth-child(2)]:border-t-0">
                <div class="border-t border-stone-200 dark:border-stone-800 p-3" data-stat="held">
                    <div class="text-stone-600 dark:text-stone-400">Held</div>
                    <div class="text-base font-semibold tabular-nums">{{ $money->held->format() }}</div>
                </div>
                <div class="border-t border-stone-200 dark:border-stone-800 p-3" data-stat="available">
                    <div class="text-stone-600 dark:text-stone-400">Available</div>
                    <div class="text-base font-semibold tabular-nums">{{ $money->available->format() }}</div>
                </div>
                <div class="border-t border-stone-200 dark:border-stone-800 p-3" data-stat="paid-out">
                    <div class="text-stone-600 dark:text-stone-400">Paid out</div>
                    <div class="text-base font-semibold tabular-nums">{{ $money->paidOut->format() }}</div>
                </div>
                <div class="border-t border-stone-200 dark:border-stone-800 p-3" data-stat="fees-earned">
                    <div class="text-stone-600 dark:text-stone-400">Fees earned</div>
                    <div class="text-base font-semibold tabular-nums">{{ $money->feesEarned->format() }}</div>
                </div>
                <div class="border-t border-stone-200 dark:border-stone-800 p-3" data-stat="fees-refunded">
                    <div class="text-stone-600 dark:text-stone-400">Fees refunded</div>
                    <div class="text-base font-semibold tabular-nums">{{ $money->feesRefunded->format() }}</div>
                </div>
                <div class="border-t border-stone-200 dark:border-stone-800 p-3" data-stat="refunded">
                    <div class="text-stone-600 dark:text-stone-400">Refunded</div>
                    <div class="text-base font-semibold tabular-nums">{{ $money->refunded->format() }}</div>
                </div>
            </div>
            <a href="{{ route('admin.accounting') }}" class="flex min-h-11 items-center justify-between gap-3 border-t border-stone-200 dark:border-stone-800 px-4 text-stone-600 dark:text-stone-400">
                <span>Accounting</span>
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" class="text-stone-400 dark:text-stone-600"><path d="M6 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
            </a>
        </div>

        <div class="rounded border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900">
            <div class="border-b border-stone-200 dark:border-stone-800 px-4 py-2 text-sm font-semibold text-stone-700 dark:text-stone-300">Listings</div>
            @foreach ($listings as $row)
                <a href="{{ route('admin.listings.index', ['status' => $row->status->value]) }}" data-status="{{ $row->status->value }}" class="flex min-h-11 items-center justify-between gap-3 border-b border-stone-100 last:border-b-0 dark:border-stone-800/60 px-4 text-stone-600 dark:text-stone-400">
                    <span>{{ $row->label() }}</span>
                    <span class="flex items-center gap-2">
                        <span class="font-semibold tabular-nums {{ $row->count === 0 ? 'text-stone-400 dark:text-stone-600' : 'text-stone-900 dark:text-stone-100' }}">{{ $row->count }}</span>
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" class="text-stone-400 dark:text-stone-600"><path d="M6 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    </span>
                </a>
            @endforeach
        </div>

        <div class="rounded border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900">
            <div class="border-b border-stone-200 dark:border-stone-800 px-4 py-2 text-sm font-semibold text-stone-700 dark:text-stone-300">Orders</div>
            @foreach ($orders as $row)
                <a href="{{ route('admin.orders.index', ['status' => $row->status->value]) }}" data-status="{{ $row->status->value }}" class="flex min-h-11 items-center justify-between gap-3 border-b border-stone-100 last:border-b-0 dark:border-stone-800/60 px-4 text-stone-600 dark:text-stone-400">
                    <span>{{ $row->label() }}</span>
                    <span class="flex items-center gap-2">
                        <span class="font-semibold tabular-nums {{ $row->count === 0 ? 'text-stone-400 dark:text-stone-600' : 'text-stone-900 dark:text-stone-100' }}">{{ $row->count }}</span>
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" class="text-stone-400 dark:text-stone-600"><path d="M6 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    </span>
                </a>
            @endforeach
        </div>

        <div class="rounded border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900">
            <div class="border-b border-stone-200 dark:border-stone-800 px-4 py-2 text-sm font-semibold text-stone-700 dark:text-stone-300">Fulfillments</div>
            @foreach ($fulfillments as $row)
                <a href="{{ route('admin.fulfillments.index', ['status' => $row->status->value]) }}" data-status="{{ $row->status->value }}" class="flex min-h-11 items-center justify-between gap-3 border-b border-stone-100 last:border-b-0 dark:border-stone-800/60 px-4 text-stone-600 dark:text-stone-400">
                    <span>{{ $row->label() }}</span>
                    <span class="flex items-center gap-2">
                        <span class="font-semibold tabular-nums {{ $row->count === 0 ? 'text-stone-400 dark:text-stone-600' : 'text-stone-900 dark:text-stone-100' }}">{{ $row->count }}</span>
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" class="text-stone-400 dark:text-stone-600"><path d="M6 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    </span>
                </a>
            @endforeach
        </div>

        <div class="rounded border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900">
            <a href="{{ route('admin.stats') }}" class="flex min-h-11 items-center justify-between gap-3 px-4 text-stone-600 dark:text-stone-400" data-stat="page-views-week">
                <span>Page views this week</span>
                <span class="flex items-center gap-2">
                    <span class="font-semibold tabular-nums text-stone-900 dark:text-stone-100">{{ number_format($pageViewsThisWeek) }}</span>
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" class="text-stone-400 dark:text-stone-600"><path d="M6 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                </span>
            </a>
        </div>
    </div>

    <div class="hidden sm:block">
        <section aria-labelledby="money-heading" class="mt-6">
            <h2 id="money-heading" class="font-semibold text-stone-700 dark:text-stone-300">Platform money</h2>

            {{-- Headline figures: the shared-borders stat-tile grid — one
                 hairline gap between cells rather than a border per card. --}}
            <div class="mt-2 grid grid-cols-2 gap-px overflow-hidden rounded-lg bg-stone-200 ring-1 ring-stone-200 sm:grid-cols-3 lg:grid-cols-6 dark:bg-white/10 dark:ring-white/10">
                <div class="bg-white p-6 dark:bg-stone-900" data-stat="held">
                    <p class="text-sm/6 font-medium text-stone-500 dark:text-stone-400">Held</p>
                    <p class="mt-1 text-3xl font-semibold tracking-tight text-stone-900 dark:text-white">{{ $money->held->format() }}</p>
                </div>
                <div class="bg-white p-6 dark:bg-stone-900" data-stat="available">
                    <p class="text-sm/6 font-medium text-stone-500 dark:text-stone-400">Available</p>
                    <p class="mt-1 text-3xl font-semibold tracking-tight text-stone-900 dark:text-white">{{ $money->available->format() }}</p>
                </div>
                <div class="bg-white p-6 dark:bg-stone-900" data-stat="paid-out">
                    <p class="text-sm/6 font-medium text-stone-500 dark:text-stone-400">Paid out</p>
                    <p class="mt-1 text-3xl font-semibold tracking-tight text-stone-900 dark:text-white">{{ $money->paidOut->format() }}</p>
                </div>
                <div class="bg-white p-6 dark:bg-stone-900" data-stat="fees-earned">
                    <p class="text-sm/6 font-medium text-stone-500 dark:text-stone-400">Fees earned</p>
                    <p class="mt-1 text-3xl font-semibold tracking-tight text-stone-900 dark:text-white">{{ $money->feesEarned->format() }}</p>
                </div>
                <div class="bg-white p-6 dark:bg-stone-900" data-stat="fees-refunded">
                    <p class="text-sm/6 font-medium text-stone-500 dark:text-stone-400">Fees refunded</p>
                    <p class="mt-1 text-3xl font-semibold tracking-tight text-stone-900 dark:text-white">{{ $money->feesRefunded->format() }}</p>
                </div>
                <div class="bg-white p-6 dark:bg-stone-900" data-stat="refunded">
                    <p class="text-sm/6 font-medium text-stone-500 dark:text-stone-400">Refunded</p>
                    <p class="mt-1 text-3xl font-semibold tracking-tight text-stone-900 dark:text-white">{{ $money->refunded->format() }}</p>
                </div>
            </div>
        </section>

        <section aria-labelledby="page-views-heading" class="mt-6">
            <h2 id="page-views-heading" class="font-semibold text-stone-700 dark:text-stone-300">Traffic</h2>

            <div class="mt-2 inline-grid grid-cols-1 gap-px overflow-hidden rounded-lg bg-stone-200 ring-1 ring-stone-200 dark:bg-white/10 dark:ring-white/10" data-stat="page-views-week">
                <div class="bg-white p-6 dark:bg-stone-900">
                    <p class="text-sm/6 font-medium text-stone-500 dark:text-stone-400">Page views this week</p>
                    <p class="mt-1 text-3xl font-semibold tracking-tight text-stone-900 dark:text-white">{{ number_format($pageViewsThisWeek) }}</p>
                </div>
            </div>
        </section>

        <section aria-labelledby="listings-heading" class="mt-6">
            <h2 id="listings-heading" class="font-semibold text-stone-700 dark:text-stone-300">Listings</h2>

            <dl class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4" data-tally="listings">
                @foreach ($listings as $row)
                    <div class="rounded border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4" data-status="{{ $row->status->value }}">
                        <dt class="text-stone-600 dark:text-stone-400">{{ $row->label() }}</dt>
                        <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $row->count }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section aria-labelledby="orders-heading" class="mt-6">
            <h2 id="orders-heading" class="font-semibold text-stone-700 dark:text-stone-300">Orders</h2>

            <dl class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4" data-tally="orders">
                @foreach ($orders as $row)
                    <div class="rounded border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4" data-status="{{ $row->status->value }}">
                        <dt class="text-stone-600 dark:text-stone-400">{{ $row->label() }}</dt>
                        <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $row->count }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section aria-labelledby="fulfillments-heading" class="mt-6">
            <h2 id="fulfillments-heading" class="font-semibold text-stone-700 dark:text-stone-300">Fulfillments</h2>

            <dl class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4" data-tally="fulfillments">
                @foreach ($fulfillments as $row)
                    <div class="rounded border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4" data-status="{{ $row->status->value }}">
                        <dt class="text-stone-600 dark:text-stone-400">{{ $row->label() }}</dt>
                        <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $row->count }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <ul class="mt-6 flex flex-wrap gap-x-6 gap-y-2">
            <li><a href="{{ route('admin.sellers.index') }}" class="text-stone-700 dark:text-stone-300 underline">Sellers</a></li>
            <li><a href="{{ route('admin.customers.index') }}" class="text-stone-700 dark:text-stone-300 underline">Customers</a></li>
            <li><a href="{{ route('admin.listings.index') }}" class="text-stone-700 dark:text-stone-300 underline">Listings</a></li>
            <li><a href="{{ route('admin.orders.index') }}" class="text-stone-700 dark:text-stone-300 underline">Orders</a></li>
            <li><a href="{{ route('admin.fulfillments.index') }}" class="text-stone-700 dark:text-stone-300 underline">Fulfillments</a></li>
            <li><a href="{{ route('admin.accounting') }}" class="text-stone-700 dark:text-stone-300 underline">Accounting</a></li>
            <li><a href="{{ route('admin.ledger') }}" class="text-stone-700 dark:text-stone-300 underline">Ledger</a></li>
            <li><a href="{{ route('admin.stats') }}" class="text-stone-700 dark:text-stone-300 underline">Site stats</a></li>
        </ul>
    </div>
</x-layouts.admin>
