<x-layouts.admin title="Site stats — Art Store admin">
    <h1 class="text-xl font-semibold">Site stats</h1>

    <section aria-labelledby="views-by-day-heading" class="mt-6">
        <h2 id="views-by-day-heading" class="font-semibold text-gray-700">Page views by day</h2>

        @if (empty($days))
            <x-admin.nothing class="mt-2">No page views recorded yet.</x-admin.nothing>
        @else
            <div class="mt-2 overflow-x-auto rounded border border-gray-300 bg-white">
                <table class="w-full text-left">
                    <caption class="sr-only">Page views for the last seven days</caption>
                    <thead class="border-b border-gray-300 bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-2 font-semibold">Day</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold">Views</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($days as $day)
                            <tr data-day="{{ $day['day'] }}">
                                <th scope="row" class="px-4 py-2 font-normal">{{ $day['day'] }}</th>
                                <td class="px-4 py-2 text-right tabular-nums" data-cell="count">{{ $day['count'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section aria-labelledby="views-by-pattern-heading" class="mt-6">
        <h2 id="views-by-pattern-heading" class="font-semibold text-gray-700">Page views by route pattern</h2>

        @if (empty($patterns))
            <x-admin.nothing class="mt-2">No page views recorded yet.</x-admin.nothing>
        @else
            <div class="mt-2 overflow-x-auto rounded border border-gray-300 bg-white">
                <table class="w-full text-left">
                    <caption class="sr-only">Page views by site and route pattern, busiest first</caption>
                    <thead class="border-b border-gray-300 bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-2 font-semibold">Site</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Pattern</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold">Views</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($patterns as $pattern)
                            <tr data-pattern="{{ $pattern['site'] }} {{ $pattern['pathPattern'] }}">
                                <td class="px-4 py-2">{{ $pattern['site'] }}</td>
                                <th scope="row" class="px-4 py-2 font-normal font-mono">{{ $pattern['pathPattern'] }}</th>
                                <td class="px-4 py-2 text-right tabular-nums" data-cell="count">{{ $pattern['count'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section aria-labelledby="listing-events-heading" class="mt-6">
        <h2 id="listing-events-heading" class="font-semibold text-gray-700">Listing events</h2>

        <dl class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ($events as $row)
                <div class="rounded border border-gray-300 bg-white p-4" data-stat="event-{{ $row->type->value }}">
                    <dt class="text-gray-600">{{ $row->label() }}</dt>
                    <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $row->count }}</dd>
                </div>
            @endforeach
        </dl>
    </section>
</x-layouts.admin>
