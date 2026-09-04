@props(['balance'])

<dl class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-3">
    <div class="rounded border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4">
        <dt class="text-stone-600 dark:text-stone-400">Held</dt>
        <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $balance->held->format() }}</dd>
    </div>
    <div class="rounded border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4">
        <dt class="text-stone-600 dark:text-stone-400">Available</dt>
        <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $balance->available->format() }}</dd>
    </div>
    <div class="rounded border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4">
        <dt class="text-stone-600 dark:text-stone-400">Paid out</dt>
        <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $balance->paidOut->format() }}</dd>
    </div>
</dl>
