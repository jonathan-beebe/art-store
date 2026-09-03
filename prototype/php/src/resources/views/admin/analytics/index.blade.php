@php
    $eventIcons = array_column(\App\Domain\Analytics\AnalyticsEventName::cases(), null, 'value');
    $eventIcons = array_map(fn (\App\Domain\Analytics\AnalyticsEventName $case): string => $case->iconPath(), $eventIcons);
    $eventIcons[\App\Domain\Analytics\EventBreakdown::PAGE_VIEW_EVENT_NAME] = \App\Domain\Analytics\EventBreakdown::PAGE_VIEW_ICON_PATH;

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

        <x-admin.analytics.search-form
            action="{{ route('admin.analytics.index') }}"
            id="analytics-search"
            :search="$search"
            placeholder="Search events, or paste a listing, customer, session, or IP"
            :carry="$roundTripped"
            class="min-w-80"
        />

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
                        <th scope="col" class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-200 dark:divide-stone-800">
                    @foreach ($events as $event)
                        @php
                            $heights = \App\Domain\Analytics\BarStrip::heights($event->daily, 26);
                            $tooltips = array_map(fn (int $count, string $day): string => \App\Domain\Analytics\AnalyticsRange::dayCaption($day).': '.number_format($count), $event->daily, $dayLabels);
                        @endphp
                        <tr class="group relative hover:bg-stone-50 dark:hover:bg-stone-800/50">
                            <th scope="row" class="relative px-4 py-2 font-normal">
                                <a href="{{ $eventHref($event->name) }}" class="flex items-center gap-2 after:absolute after:inset-0 after:content-['']">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 text-stone-500 dark:text-stone-400"><path d="{{ $eventIcons[$event->name] ?? '' }}"></path></svg>
                                    <span class="flex flex-col">
                                        <span class="font-medium text-stone-900 hover:text-stone-700 dark:text-stone-100 dark:hover:text-stone-300">{{ $event->label }}</span>
                                        <span class="font-mono text-[11px] text-stone-500 dark:text-stone-400">{{ $event->name }}</span>
                                    </span>
                                </a>
                            </th>
                            <td class="relative px-4 py-2 text-right font-semibold tabular-nums text-stone-900 dark:text-stone-100">
                                {{ number_format($event->current) }}
                                <x-admin.analytics.stretched-link :href="$eventHref($event->name)" />
                            </td>
                            <td class="relative px-4 py-2 text-right tabular-nums text-stone-600 dark:text-stone-400">
                                {{ number_format($event->previous) }}
                                <x-admin.analytics.stretched-link :href="$eventHref($event->name)" />
                            </td>
                            <td class="relative px-4 py-2 text-right tabular-nums">
                                <span class="{{ $deltaClass($event->change) }}">{{ $event->change->text }}</span>
                                <x-admin.analytics.stretched-link :href="$eventHref($event->name)" />
                            </td>
                            <td class="relative px-4 py-2">
                                <div class="flex h-7 w-[180px] items-end gap-0.5">
                                    @foreach ($heights as $i => $px)
                                        <div title="{{ $tooltips[$i] }}" style="height: {{ $px }}px" class="min-h-0.5 flex-1 rounded-t-sm bg-stone-400 dark:bg-stone-500"></div>
                                    @endforeach
                                </div>
                                <x-admin.analytics.stretched-link :href="$eventHref($event->name)" />
                            </td>
                            <td class="relative px-4 py-2 text-right tabular-nums">
                                {{ $event->subjects === null ? '—' : number_format($event->subjects) }}
                                <x-admin.analytics.stretched-link :href="$eventHref($event->name)" />
                            </td>
                            <td class="relative px-4 py-2 text-right tabular-nums">
                                {{ $event->actors === null ? '—' : number_format($event->actors) }}
                                <x-admin.analytics.stretched-link :href="$eventHref($event->name)" />
                            </td>
                            <td class="relative px-4 py-2 text-stone-400 dark:text-stone-500">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3l5 5-5 5"></path></svg>
                                <x-admin.analytics.stretched-link :href="$eventHref($event->name)" />
                            </td>
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
                            <th scope="col" class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-200 dark:divide-stone-800">
                        @foreach ($actors as $actor)
                            @php $actorHref = route('admin.analytics.actors.show', $actor->id); @endphp
                            <tr class="group relative hover:bg-stone-50 dark:hover:bg-stone-800/50">
                                <th scope="row" class="relative px-4 py-2 font-normal">
                                    <x-admin.log-id-chip :id="$actor->id" :href="$actorHref" class="after:absolute after:inset-0 after:content-['']" />
                                </th>
                                <td class="relative px-4 py-2">
                                    <div class="flex items-center gap-2">
                                        <x-admin.status-badge :tint="$actor->kind === 'verified' ? 'ok' : 'neutral'">{{ $actor->kind }}</x-admin.status-badge>
                                        <span class="text-stone-600 dark:text-stone-400">{{ $actor->who }}</span>
                                    </div>
                                    <x-admin.analytics.stretched-link :href="$actorHref" />
                                </td>
                                <td class="relative px-4 py-2 font-mono text-xs text-stone-600 dark:text-stone-400">
                                    {{ implode(', ', $actor->ips) }}
                                    <x-admin.analytics.stretched-link :href="$actorHref" />
                                </td>
                                <td class="relative px-4 py-2 text-right font-semibold tabular-nums text-stone-900 dark:text-stone-100">
                                    {{ number_format($actor->events) }}
                                    <x-admin.analytics.stretched-link :href="$actorHref" />
                                </td>
                                <td class="relative px-4 py-2 text-right tabular-nums {{ $actor->flagged ? 'font-bold text-red-700 dark:text-red-500' : 'text-stone-900 dark:text-stone-100' }}">
                                    {{ number_format($actor->peakPerHour) }}
                                    <x-admin.analytics.stretched-link :href="$actorHref" />
                                </td>
                                <td class="relative px-4 py-2 text-right tabular-nums">
                                    {{ number_format($actor->subjects) }}
                                    <x-admin.analytics.stretched-link :href="$actorHref" />
                                </td>
                                <td class="relative px-4 py-2 font-mono text-[11px] whitespace-nowrap text-stone-500 dark:text-stone-400">
                                    {{ \App\Support\RelativeTime::short($actor->lastSeenAt, $now) }}
                                    <x-admin.analytics.stretched-link :href="$actorHref" />
                                </td>
                                <td class="relative px-4 py-2 text-stone-400 dark:text-stone-500">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3l5 5-5 5"></path></svg>
                                    <x-admin.analytics.stretched-link :href="$actorHref" />
                                </td>
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
