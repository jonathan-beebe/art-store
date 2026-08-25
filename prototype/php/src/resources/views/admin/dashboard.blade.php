<x-layouts.admin title="Dashboard — Art Store admin">
    <h1 class="text-xl font-semibold">Dashboard</h1>

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
</x-layouts.admin>
