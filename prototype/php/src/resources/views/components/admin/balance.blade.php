@props(['balance'])

<dl class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-3">
    <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
        <dt class="text-gray-600 dark:text-gray-400">Held</dt>
        <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $balance->held->format() }}</dd>
    </div>
    <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
        <dt class="text-gray-600 dark:text-gray-400">Available</dt>
        <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $balance->available->format() }}</dd>
    </div>
    <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
        <dt class="text-gray-600 dark:text-gray-400">Paid out</dt>
        <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $balance->paidOut->format() }}</dd>
    </div>
</dl>
