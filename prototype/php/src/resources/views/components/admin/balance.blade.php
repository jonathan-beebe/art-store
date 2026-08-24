@props(['balance'])

<dl class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-3">
    <div class="rounded border border-gray-300 bg-white p-4">
        <dt class="text-gray-600">Held</dt>
        <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $balance->held->format() }}</dd>
    </div>
    <div class="rounded border border-gray-300 bg-white p-4">
        <dt class="text-gray-600">Available</dt>
        <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $balance->available->format() }}</dd>
    </div>
    <div class="rounded border border-gray-300 bg-white p-4">
        <dt class="text-gray-600">Paid out</dt>
        <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $balance->paidOut->format() }}</dd>
    </div>
</dl>
