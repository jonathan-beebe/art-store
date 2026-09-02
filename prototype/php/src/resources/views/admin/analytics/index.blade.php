@php
    $eventIcons = [
        'listing.view' => 'M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178zM15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z',
        'listing.favorite' => 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z',
        'listing.unfavorite' => 'M3 3l18 18M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s2.2-1.17 4.6-3.3',
        'listing.cart_add' => 'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0z',
        'page.view' => 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9z',
    ];

    $deltaClasses = [
        'up' => 'text-green-700 dark:text-green-400 font-medium',
        'down' => 'text-red-700 dark:text-red-500 font-medium',
        'flat' => 'text-stone-500 dark:text-stone-400',
    ];

    $segmentActive = 'rounded-md px-2.5 py-1 text-xs font-medium bg-stone-900 dark:bg-stone-100 text-white dark:text-stone-900';
    $segmentIdle = 'rounded-md px-2.5 py-1 text-xs font-medium bg-stone-100 dark:bg-stone-400/10 text-stone-600 dark:text-stone-400 hover:bg-stone-200 dark:hover:bg-stone-400/20';

    $deltaClass = function (\App\Domain\Analytics\RangeChange $change) use ($deltaClasses): string {
        return match ($change->direction) {
            \App\Domain\Analytics\ChangeDirection::Up => $deltaClasses['up'],
            \App\Domain\Analytics\ChangeDirection::Down => $deltaClasses['down'],
            \App\Domain\Analytics\ChangeDirection::Flat => $deltaClasses['flat'],
        };
    };

    $eventHref = fn (string $name): string => route('admin.analytics.events.show', array_filter([
        'name' => $name,
        'range' => $roundTripped['range'] ?? null,
    ]));
@endphp

<x-layouts.admin title="Analytics — Art Store admin" mode="content-wide">
    <div class="flex flex-wrap items-baseline gap-3">
        <h1 class="text-xl font-semibold">Analytics</h1>
        <span class="text-stone-600 dark:text-stone-400">{{ $rangeCaption }}</span>
    </div>

    {{-- toolbar --}}
    <div class="mt-4 flex flex-wrap items-center gap-3 rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4">
        <div role="group" aria-label="Range" class="flex gap-1">
            @foreach ($rangeLinks as $link)
                <a href="{{ $link['href'] }}" @if ($link['active']) aria-current="true" @endif class="{{ $link['active'] ? $segmentActive : $segmentIdle }}">{{ $link['label'] }}</a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.analytics.index') }}" class="flex min-w-80 flex-1 items-center gap-2">
            <input type="hidden" name="range" value="{{ $roundTripped['range'] ?? '' }}">
            <input type="hidden" name="actors" value="{{ $roundTripped['actors'] ?? '' }}">
            <label for="analytics-search" class="sr-only">Search events, or paste a listing, customer, session, or IP</label>
            <input id="analytics-search" name="q" type="text" value="{{ $search }}" placeholder="Search events, or paste a listing, customer, session, or IP" class="mt-1 block w-full rounded-md bg-white px-3 py-2 text-stone-900 inset-ring inset-ring-stone-300 focus:outline-2 focus:-outline-offset-2 focus:outline-stone-600 dark:bg-white/5 dark:text-stone-100 dark:inset-ring-white/10">
            <button type="submit" class="inline-flex min-h-11 items-center rounded-md bg-stone-900 dark:bg-stone-100 px-4 text-sm font-semibold text-white dark:text-stone-900 shadow-xs hover:bg-stone-800 dark:hover:bg-stone-200">Search</button>
        </form>

        <div role="group" aria-label="Actors" class="flex gap-1">
            @foreach ($actorFilterLinks as $link)
                <a href="{{ $link['href'] }}" @if ($link['active']) aria-current="true" @endif class="{{ $link['active'] ? $segmentActive : $segmentIdle }}">{{ $link['label'] }}</a>
            @endforeach
        </div>
    </div>

    {{-- jump row --}}
    @if ($jump)
        <a href="{{ $jump->kind === \App\Domain\Analytics\JumpKind::Listing ? route('admin.analytics.listings.show', $jump->id) : route('admin.analytics.actors.show', $jump->id) }}" class="mt-4 flex items-center gap-3 rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4 hover:bg-stone-50 dark:hover:bg-stone-800/50">
            <x-admin.log-id-chip :id="$jump->id" :truncate="false" />
            <span class="text-stone-600 dark:text-stone-400">{{ $jump->caption }}</span>
            <span class="ml-auto text-stone-700 dark:text-stone-300 underline">Open its events</span>
        </a>
    @endif

    {{-- events --}}
    <section aria-labelledby="analytics-events-heading" class="mt-6">
        <h2 id="analytics-events-heading" class="font-semibold text-stone-700 dark:text-stone-300">Events</h2>

        <div class="mt-2 hidden overflow-x-auto rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 sm:block">
            <table class="w-full text-left">
                <caption class="sr-only">Every event name compared against the range before it</caption>
                <thead class="border-b border-stone-300 dark:border-stone-700 bg-stone-50 dark:bg-stone-800/50">
                    <tr>
                        <th scope="col" class="px-4 py-2 font-semibold whitespace-nowrap">Event</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold whitespace-nowrap">This range</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold whitespace-nowrap">Previous</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold whitespace-nowrap">Change</th>
                        <th scope="col" class="px-4 py-2 font-semibold whitespace-nowrap">By day</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold whitespace-nowrap">Subjects</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold whitespace-nowrap">Actors</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-200 dark:divide-stone-800">
                    @foreach ($events as $event)
                        @php
                            $heights = \App\Domain\Analytics\BarStrip::heights($event->daily, 26);
                            $tooltips = array_map(fn (int $count, string $day): string => \App\Domain\Analytics\AnalyticsRange::dayCaption($day).': '.number_format($count), $event->daily, $dayLabels);
                        @endphp
                        <tr>
                            <th scope="row" class="px-4 py-2 font-normal">
                                <a href="{{ $eventHref($event->name) }}" class="flex items-center gap-2 underline">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 text-stone-500 dark:text-stone-400"><path d="{{ $eventIcons[$event->name] ?? '' }}"></path></svg>
                                    <span class="flex flex-col">
                                        <span class="font-medium text-stone-900 dark:text-stone-100">{{ $event->label }}</span>
                                        <span class="font-mono text-[11px] text-stone-500 dark:text-stone-400">{{ $event->name }}</span>
                                    </span>
                                </a>
                            </th>
                            <td class="px-4 py-2 text-right font-semibold tabular-nums text-stone-900 dark:text-stone-100">{{ number_format($event->current) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums text-stone-600 dark:text-stone-400">{{ number_format($event->previous) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">
                                <span class="{{ $deltaClass($event->change) }}">{{ $event->change->text }}</span>
                            </td>
                            <td class="px-4 py-2">
                                <div class="flex h-7 w-[180px] items-end gap-0.5">
                                    @foreach ($heights as $i => $px)
                                        <div title="{{ $tooltips[$i] }}" style="height: {{ $px }}px" class="min-h-0.5 flex-1 rounded-t-sm bg-stone-400 dark:bg-stone-500"></div>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $event->subjects === null ? '—' : number_format($event->subjects) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $event->actors === null ? '—' : number_format($event->actors) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-admin.card-list caption="Every event name compared against the range before it">
            @foreach ($events as $event)
                <x-admin.card-row>
                    <a href="{{ $eventHref($event->name) }}" class="flex items-center justify-between gap-3">
                        <span class="flex flex-col">
                            <span class="font-medium text-stone-900 dark:text-white">{{ $event->label }}</span>
                            <span class="font-mono text-[11px] text-stone-500 dark:text-stone-400">{{ $event->name }}</span>
                        </span>
                        <span class="{{ $deltaClass($event->change) }}">{{ $event->change->text }}</span>
                    </a>
                    <div class="flex items-center justify-between text-stone-600 dark:text-stone-400">
                        <span>{{ number_format($event->current) }} this range · {{ number_format($event->previous) }} previous</span>
                    </div>
                </x-admin.card-row>
            @endforeach
        </x-admin.card-list>
    </section>

    {{-- actors by velocity --}}
    <section aria-labelledby="analytics-actors-heading" class="mt-6">
        <div class="flex flex-wrap items-baseline gap-3">
            <h2 id="analytics-actors-heading" class="font-semibold text-stone-700 dark:text-stone-300">Actors by velocity</h2>
            <span class="text-xs text-stone-500 dark:text-stone-400">busiest hour in the range, every event type</span>
            <a href="{{ $allActorsHref }}" class="ml-auto inline-flex items-center gap-1 underline">All actors</a>
        </div>

        @if (empty($actors))
            <x-admin.nothing class="mt-2">No actor activity in this range.</x-admin.nothing>
        @else
            <div class="mt-2 hidden overflow-x-auto rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 sm:block">
                <table class="w-full text-left">
                    <caption class="sr-only">Actors ranked by their busiest hour in the range</caption>
                    <thead class="border-b border-stone-300 dark:border-stone-700 bg-stone-50 dark:bg-stone-800/50">
                        <tr>
                            <th scope="col" class="px-4 py-2 font-semibold whitespace-nowrap">Actor</th>
                            <th scope="col" class="px-4 py-2 font-semibold whitespace-nowrap">Identity</th>
                            <th scope="col" class="px-4 py-2 font-semibold whitespace-nowrap">IPs</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold whitespace-nowrap">Events</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold whitespace-nowrap">Peak / hour</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold whitespace-nowrap">Subjects</th>
                            <th scope="col" class="px-4 py-2 font-semibold whitespace-nowrap">Last seen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-200 dark:divide-stone-800">
                        @foreach ($actors as $actor)
                            <tr>
                                <th scope="row" class="px-4 py-2 font-normal">
                                    <a href="{{ route('admin.analytics.actors.show', $actor->id) }}" class="underline">
                                        <x-admin.log-id-chip :id="$actor->id" />
                                    </a>
                                </th>
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-2">
                                        <x-admin.status-badge :tint="$actor->kind === 'verified' ? 'ok' : 'neutral'">{{ $actor->kind }}</x-admin.status-badge>
                                        <span class="text-stone-600 dark:text-stone-400">{{ $actor->who }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-2 font-mono text-xs text-stone-600 dark:text-stone-400">{{ implode(', ', $actor->ips) }}</td>
                                <td class="px-4 py-2 text-right font-semibold tabular-nums text-stone-900 dark:text-stone-100">{{ number_format($actor->events) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums {{ $actor->flagged ? 'font-bold text-red-700 dark:text-red-500' : 'text-stone-900 dark:text-stone-100' }}">{{ number_format($actor->peakPerHour) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ number_format($actor->subjects) }}</td>
                                <td class="px-4 py-2 font-mono text-[11px] whitespace-nowrap text-stone-500 dark:text-stone-400">{{ \App\Support\RelativeTime::short($actor->lastSeenAt, $now) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-admin.card-list caption="Actors ranked by their busiest hour in the range">
                @foreach ($actors as $actor)
                    <x-admin.card-row href="{{ route('admin.analytics.actors.show', $actor->id) }}">
                        <div class="flex items-center justify-between gap-3">
                            <x-admin.log-id-chip :id="$actor->id" />
                            <span class="{{ $actor->flagged ? 'font-bold text-red-700 dark:text-red-500' : 'text-stone-900 dark:text-white' }}">{{ number_format($actor->peakPerHour) }}/h</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-admin.status-badge :tint="$actor->kind === 'verified' ? 'ok' : 'neutral'">{{ $actor->kind }}</x-admin.status-badge>
                            <span class="text-stone-600 dark:text-stone-400">{{ $actor->who }}</span>
                        </div>
                        <div class="flex items-center justify-between text-stone-600 dark:text-stone-400">
                            <span>{{ number_format($actor->events) }} events</span>
                            <span>{{ \App\Support\RelativeTime::short($actor->lastSeenAt, $now) }}</span>
                        </div>
                    </x-admin.card-row>
                @endforeach
            </x-admin.card-list>
        @endif
    </section>
</x-layouts.admin>
