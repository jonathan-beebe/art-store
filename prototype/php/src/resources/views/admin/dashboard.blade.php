<x-layouts.admin title="Dashboard — Art Store admin">
    <h1 class="text-xl font-semibold">Dashboard</h1>

    {{-- Below `sm`: the dashboard is a drill-down hub — every status count
         is a link into its filtered list, grouped in cards. At `sm` and
         up: today's static tally grids, unchanged. --}}
    <div class="mt-6 flex flex-col gap-4 sm:hidden">
        <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">
            <div class="border-b border-gray-200 dark:border-gray-800 px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300">Platform money</div>
            <div class="grid grid-cols-2 divide-x divide-y divide-gray-200 dark:divide-gray-800 [&>div:nth-child(1)]:border-t-0 [&>div:nth-child(2)]:border-t-0">
                <div class="border-t border-gray-200 dark:border-gray-800 p-3" data-stat="held">
                    <div class="text-gray-600 dark:text-gray-400">Held</div>
                    <div class="text-base font-semibold tabular-nums">{{ $money->held->format() }}</div>
                </div>
                <div class="border-t border-gray-200 dark:border-gray-800 p-3" data-stat="available">
                    <div class="text-gray-600 dark:text-gray-400">Available</div>
                    <div class="text-base font-semibold tabular-nums">{{ $money->available->format() }}</div>
                </div>
                <div class="border-t border-gray-200 dark:border-gray-800 p-3" data-stat="paid-out">
                    <div class="text-gray-600 dark:text-gray-400">Paid out</div>
                    <div class="text-base font-semibold tabular-nums">{{ $money->paidOut->format() }}</div>
                </div>
                <div class="border-t border-gray-200 dark:border-gray-800 p-3" data-stat="fees-earned">
                    <div class="text-gray-600 dark:text-gray-400">Fees earned</div>
                    <div class="text-base font-semibold tabular-nums">{{ $money->feesEarned->format() }}</div>
                </div>
                <div class="border-t border-gray-200 dark:border-gray-800 p-3" data-stat="fees-refunded">
                    <div class="text-gray-600 dark:text-gray-400">Fees refunded</div>
                    <div class="text-base font-semibold tabular-nums">{{ $money->feesRefunded->format() }}</div>
                </div>
                <div class="border-t border-gray-200 dark:border-gray-800 p-3" data-stat="refunded">
                    <div class="text-gray-600 dark:text-gray-400">Refunded</div>
                    <div class="text-base font-semibold tabular-nums">{{ $money->refunded->format() }}</div>
                </div>
            </div>
            <a href="{{ route('admin.accounting') }}" class="flex min-h-11 items-center justify-between gap-3 border-t border-gray-200 dark:border-gray-800 px-4 text-gray-600 dark:text-gray-400">
                <span>Accounting</span>
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" class="text-gray-400 dark:text-gray-600"><path d="M6 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
            </a>
        </div>

        <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">
            <div class="border-b border-gray-200 dark:border-gray-800 px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300">Listings</div>
            @foreach ($listings as $row)
                <a href="{{ route('admin.listings.index', ['status' => $row->status->value]) }}" data-status="{{ $row->status->value }}" class="flex min-h-11 items-center justify-between gap-3 border-b border-gray-100 last:border-b-0 dark:border-gray-800/60 px-4 text-gray-600 dark:text-gray-400">
                    <span>{{ $row->label() }}</span>
                    <span class="flex items-center gap-2">
                        <span class="font-semibold tabular-nums {{ $row->count === 0 ? 'text-gray-400 dark:text-gray-600' : 'text-gray-900 dark:text-gray-100' }}">{{ $row->count }}</span>
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" class="text-gray-400 dark:text-gray-600"><path d="M6 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    </span>
                </a>
            @endforeach
        </div>

        <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">
            <div class="border-b border-gray-200 dark:border-gray-800 px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300">Orders</div>
            @foreach ($orders as $row)
                <a href="{{ route('admin.orders.index', ['status' => $row->status->value]) }}" data-status="{{ $row->status->value }}" class="flex min-h-11 items-center justify-between gap-3 border-b border-gray-100 last:border-b-0 dark:border-gray-800/60 px-4 text-gray-600 dark:text-gray-400">
                    <span>{{ $row->label() }}</span>
                    <span class="flex items-center gap-2">
                        <span class="font-semibold tabular-nums {{ $row->count === 0 ? 'text-gray-400 dark:text-gray-600' : 'text-gray-900 dark:text-gray-100' }}">{{ $row->count }}</span>
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" class="text-gray-400 dark:text-gray-600"><path d="M6 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    </span>
                </a>
            @endforeach
        </div>

        <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">
            <div class="border-b border-gray-200 dark:border-gray-800 px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300">Fulfillments</div>
            @foreach ($fulfillments as $row)
                <a href="{{ route('admin.fulfillments.index', ['status' => $row->status->value]) }}" data-status="{{ $row->status->value }}" class="flex min-h-11 items-center justify-between gap-3 border-b border-gray-100 last:border-b-0 dark:border-gray-800/60 px-4 text-gray-600 dark:text-gray-400">
                    <span>{{ $row->label() }}</span>
                    <span class="flex items-center gap-2">
                        <span class="font-semibold tabular-nums {{ $row->count === 0 ? 'text-gray-400 dark:text-gray-600' : 'text-gray-900 dark:text-gray-100' }}">{{ $row->count }}</span>
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" class="text-gray-400 dark:text-gray-600"><path d="M6 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    </span>
                </a>
            @endforeach
        </div>

        <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">
            <a href="{{ route('admin.stats') }}" class="flex min-h-11 items-center justify-between gap-3 px-4 text-gray-600 dark:text-gray-400" data-stat="page-views-week">
                <span>Page views this week</span>
                <span class="flex items-center gap-2">
                    <span class="font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ number_format($pageViewsThisWeek) }}</span>
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" class="text-gray-400 dark:text-gray-600"><path d="M6 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                </span>
            </a>
        </div>
    </div>

    <div class="hidden sm:block">
        <section aria-labelledby="money-heading" class="mt-6">
            <h2 id="money-heading" class="font-semibold text-gray-700 dark:text-gray-300">Platform money</h2>

            <dl class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4" data-stat="held">
                    <dt class="text-gray-600 dark:text-gray-400">Held</dt>
                    <dd class="mt-1 text-xl font-semibold tabular-nums">{{ $money->held->format() }}</dd>
                </div>
                <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4" data-stat="available">
                    <dt class="text-gray-600 dark:text-gray-400">Available</dt>
                    <dd class="mt-1 text-xl font-semibold tabular-nums">{{ $money->available->format() }}</dd>
                </div>
                <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4" data-stat="paid-out">
                    <dt class="text-gray-600 dark:text-gray-400">Paid out</dt>
                    <dd class="mt-1 text-xl font-semibold tabular-nums">{{ $money->paidOut->format() }}</dd>
                </div>
                <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4" data-stat="fees-earned">
                    <dt class="text-gray-600 dark:text-gray-400">Fees earned</dt>
                    <dd class="mt-1 text-xl font-semibold tabular-nums">{{ $money->feesEarned->format() }}</dd>
                </div>
                <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4" data-stat="fees-refunded">
                    <dt class="text-gray-600 dark:text-gray-400">Fees refunded</dt>
                    <dd class="mt-1 text-xl font-semibold tabular-nums">{{ $money->feesRefunded->format() }}</dd>
                </div>
                <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4" data-stat="refunded">
                    <dt class="text-gray-600 dark:text-gray-400">Refunded</dt>
                    <dd class="mt-1 text-xl font-semibold tabular-nums">{{ $money->refunded->format() }}</dd>
                </div>
            </dl>
        </section>

        <section aria-labelledby="page-views-heading" class="mt-6">
            <h2 id="page-views-heading" class="font-semibold text-gray-700 dark:text-gray-300">Traffic</h2>

            <div class="mt-2 inline-block rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4" data-stat="page-views-week">
                <dt class="text-gray-600 dark:text-gray-400">Page views this week</dt>
                <dd class="mt-1 text-xl font-semibold tabular-nums">{{ number_format($pageViewsThisWeek) }}</dd>
            </div>
        </section>

        <section aria-labelledby="listings-heading" class="mt-6">
            <h2 id="listings-heading" class="font-semibold text-gray-700 dark:text-gray-300">Listings</h2>

            <dl class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4" data-tally="listings">
                @foreach ($listings as $row)
                    <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4" data-status="{{ $row->status->value }}">
                        <dt class="text-gray-600 dark:text-gray-400">{{ $row->label() }}</dt>
                        <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $row->count }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section aria-labelledby="orders-heading" class="mt-6">
            <h2 id="orders-heading" class="font-semibold text-gray-700 dark:text-gray-300">Orders</h2>

            <dl class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4" data-tally="orders">
                @foreach ($orders as $row)
                    <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4" data-status="{{ $row->status->value }}">
                        <dt class="text-gray-600 dark:text-gray-400">{{ $row->label() }}</dt>
                        <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $row->count }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section aria-labelledby="fulfillments-heading" class="mt-6">
            <h2 id="fulfillments-heading" class="font-semibold text-gray-700 dark:text-gray-300">Fulfillments</h2>

            <dl class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4" data-tally="fulfillments">
                @foreach ($fulfillments as $row)
                    <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4" data-status="{{ $row->status->value }}">
                        <dt class="text-gray-600 dark:text-gray-400">{{ $row->label() }}</dt>
                        <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $row->count }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <ul class="mt-6 flex flex-wrap gap-x-6 gap-y-2">
            <li><a href="{{ route('admin.sellers.index') }}" class="text-gray-700 dark:text-gray-300 underline">Sellers</a></li>
            <li><a href="{{ route('admin.customers.index') }}" class="text-gray-700 dark:text-gray-300 underline">Customers</a></li>
            <li><a href="{{ route('admin.listings.index') }}" class="text-gray-700 dark:text-gray-300 underline">Listings</a></li>
            <li><a href="{{ route('admin.orders.index') }}" class="text-gray-700 dark:text-gray-300 underline">Orders</a></li>
            <li><a href="{{ route('admin.fulfillments.index') }}" class="text-gray-700 dark:text-gray-300 underline">Fulfillments</a></li>
            <li><a href="{{ route('admin.accounting') }}" class="text-gray-700 dark:text-gray-300 underline">Accounting</a></li>
            <li><a href="{{ route('admin.ledger') }}" class="text-gray-700 dark:text-gray-300 underline">Ledger</a></li>
            <li><a href="{{ route('admin.stats') }}" class="text-gray-700 dark:text-gray-300 underline">Site stats</a></li>
        </ul>
    </div>
</x-layouts.admin>
