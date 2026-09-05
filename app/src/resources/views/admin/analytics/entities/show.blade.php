@php
    $segmentActive = 'rounded-md px-2.5 py-1 text-xs font-medium bg-stone-900 dark:bg-stone-100 text-white dark:text-stone-900';
    $segmentIdle = 'rounded-md px-2.5 py-1 text-xs font-medium bg-stone-100 dark:bg-stone-400/10 text-stone-600 dark:text-stone-400 hover:bg-stone-200 dark:hover:bg-stone-400/20';

    $actionClasses = [
        'primary' => 'inline-flex min-h-11 items-center justify-center rounded-md bg-stone-900 dark:bg-stone-100 px-4 text-sm font-semibold text-white dark:text-stone-900 shadow-xs hover:bg-stone-800 dark:hover:bg-stone-200',
        'secondary' => 'inline-flex min-h-11 items-center justify-center gap-1.5 rounded-md bg-white dark:bg-white/10 px-3 py-1.5 text-sm font-semibold text-stone-900 dark:text-white shadow-xs inset-ring inset-ring-stone-300 dark:inset-ring-white/5 hover:bg-stone-50 dark:hover:bg-white/20',
        'danger' => 'inline-flex min-h-11 items-center justify-center rounded-md bg-red-600 dark:bg-red-700 px-4 text-sm font-semibold text-white shadow-xs hover:bg-red-500 dark:hover:bg-red-600',
    ];

    $badgeTint = $activity->kind === 'verified' ? 'ok' : 'neutral';

    $otherHref = fn (\App\Analytics\Admin\EntityFeedRow $row): string => match ($row->otherKind) {
        \App\Domain\Analytics\FeedOtherKind::Listing => route('admin.analytics.listings.show', ['listing' => $row->otherId]),
        \App\Domain\Analytics\FeedOtherKind::Order => route('admin.orders.show', ['order' => $row->otherId]),
        \App\Domain\Analytics\FeedOtherKind::Store => route('admin.analytics.stores.show', ['store' => $row->otherId]),
        default => route('admin.analytics.actors.show', ['customer' => $row->otherId]),
    };
@endphp

<x-layouts.admin :title="$activity->title.' — Art Store admin'" mode="content-wide">
    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ $backHref }}" class="inline-flex items-center gap-1.5 text-stone-600 dark:text-stone-400 hover:text-stone-900 dark:hover:text-stone-100">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M10 4L6 8l4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
            <span>{{ $backLabel }}</span>
        </a>
        <span class="ml-auto text-stone-600 dark:text-stone-400">{{ $rangeCaption }}</span>
        <div role="group" aria-label="Range" class="flex gap-1">
            @foreach ($rangeLinks as $link)
                <a href="{{ $link['href'] }}" @if ($link['active']) aria-current="true" @endif class="{{ $link['active'] ? $segmentActive : $segmentIdle }}">{{ $link['label'] }}</a>
            @endforeach
        </div>
    </div>

    {{-- identity card --}}
    <div class="mt-4 flex flex-col items-start gap-4 rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4 sm:flex-row">
        <div class="flex min-w-0 flex-1 flex-col gap-2">
            <div class="flex items-center gap-2">
                <x-admin.status-badge :tint="$badgeTint">{{ $activity->kind }}</x-admin.status-badge>
                <x-admin.log-id-chip :id="$activity->id" :truncate="false" />
            </div>
            <h1 class="mt-1 flex flex-wrap items-center gap-3 text-lg font-semibold text-stone-900 dark:text-stone-100">{{ $activity->title }}</h1>
            <dl class="mt-2 grid grid-cols-1 gap-x-6 gap-y-1.5 sm:grid-cols-2">
                @foreach ($activity->facts as $fact)
                    <div class="flex items-baseline justify-between gap-4 sm:justify-start sm:gap-2">
                        <dt class="text-stone-600 dark:text-stone-400">{{ $fact->label }}</dt>
                        <dd class="font-mono text-xs text-stone-700 dark:text-stone-300">{{ $fact->value }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
        <div class="flex w-full flex-col gap-2 sm:w-52">
            @foreach ($actions as $action)
                <a href="{{ $action['href'] }}" class="{{ $actionClasses[$action['variant']] }}">{{ $action['label'] }}</a>
            @endforeach
        </div>
    </div>

    @if ($activity->flagged)
        <div class="mt-4 flex items-start gap-2.5 rounded border border-amber-300 dark:border-amber-900 bg-amber-50 dark:bg-amber-950/30 p-3 text-amber-900 dark:text-amber-200">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 shrink-0" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.3 3.9L2.8 17a2 2 0 0 0 1.7 3h15a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"></path></svg>
            <span>{{ $activity->flagText }}</span>
        </div>
    @endif

    {{-- visits: an actor's own first-touch rows — a visit belongs to a session, so a listing or a store carries none --}}
    @if ($activity->kind === 'verified' || $activity->kind === 'anonymous')
        <section aria-labelledby="analytics-entity-visits-heading" class="mt-4">
            <h2 id="analytics-entity-visits-heading" class="font-semibold text-stone-700 dark:text-stone-300">Visits</h2>

            @if (empty($activity->visits))
                <x-admin.nothing class="mt-2">No visits recorded.</x-admin.nothing>
            @else
                <div class="mt-2 overflow-x-auto rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900">
                    <table class="w-full text-left">
                        <caption class="sr-only">This actor's own visits, newest first</caption>
                        <thead class="border-b border-stone-300 dark:border-stone-700 bg-stone-50 dark:bg-stone-800/50">
                            <tr>
                                <th scope="col" class="px-4 py-2 font-semibold whitespace-nowrap">First seen</th>
                                <th scope="col" class="px-4 py-2 font-semibold whitespace-nowrap">Channel</th>
                                <th scope="col" class="px-4 py-2 font-semibold whitespace-nowrap">Landing path</th>
                                <th scope="col" class="px-4 py-2 font-semibold whitespace-nowrap">Referrer</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-200 dark:divide-stone-800">
                            @foreach ($activity->visits as $visit)
                                <tr>
                                    <td class="px-4 py-2 font-mono text-[11px] whitespace-nowrap text-stone-500 dark:text-stone-400">{{ $visit->firstSeenAt->format('Y-m-d H:i') }} UTC</td>
                                    <td class="px-4 py-2 text-stone-700 dark:text-stone-300">{{ $visit->channel->label }}</td>
                                    <td class="px-4 py-2 font-mono text-xs text-stone-700 dark:text-stone-300">{{ $visit->landingPath }}</td>
                                    <td class="px-4 py-2 text-stone-600 dark:text-stone-400">{{ $visit->referrerHost ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    {{-- tiles --}}
    <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
        @foreach ($activity->tiles as $tile)
            <div class="rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4">
                <dt class="text-stone-600 dark:text-stone-400">{{ $tile->label }}</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums text-stone-900 dark:text-stone-100">{{ $tile->value }}</dd>
                <div class="mt-0.5 text-xs text-stone-500 dark:text-stone-400">{{ $tile->note }}</div>
            </div>
        @endforeach
    </dl>

    {{-- funnel --}}
    @isset($funnel)
        <section aria-labelledby="analytics-entity-funnel-heading" class="mt-6">
            <h2 id="analytics-entity-funnel-heading" class="font-semibold text-stone-700 dark:text-stone-300">Funnel</h2>

            <x-admin.analytics.funnel :funnel="$funnel" />
        </section>
    @endisset

    {{-- strip --}}
    <section class="mt-6">
        <h2 class="font-semibold text-stone-700 dark:text-stone-300">{{ $activity->stripTitle }}</h2>
        <div class="mt-2 rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4">
            <x-bar-strip :bars="$activity->strip" :height="72" class="text-stone-500 dark:text-stone-400" />
            <div class="mt-1.5 flex justify-between text-[11px] text-stone-500 dark:text-stone-400">
                <span>{{ $activity->stripFirst }}</span>
                <span>{{ $activity->stripLast }}</span>
            </div>
        </div>
    </section>

    {{-- feed --}}
    <section aria-labelledby="analytics-entity-feed-heading" class="mt-6">
        <div class="flex flex-wrap items-center gap-3">
            <h2 id="analytics-entity-feed-heading" class="font-semibold text-stone-700 dark:text-stone-300">Events</h2>
            <div role="group" aria-label="Event" class="flex flex-wrap gap-1">
                @foreach ($eventLinks as $link)
                    <a href="{{ $link['href'] }}" @if ($link['active']) aria-current="true" @endif class="{{ $link['active'] ? $segmentActive : $segmentIdle }}">{{ $link['label'] }}</a>
                @endforeach
            </div>
            <span class="ml-auto text-xs text-stone-500 dark:text-stone-400">{{ $activity->feedCaption }}</span>
        </div>

        @if (empty($activity->feed))
            <x-admin.nothing class="mt-2">No events in this range.</x-admin.nothing>
        @else
            <div class="mt-2 rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4">
                <ul class="flex flex-col">
                    @foreach ($activity->feed as $row)
                        <li class="relative flex gap-x-4">
                            <div class="absolute left-0 top-0 flex w-8 justify-center {{ $loop->last ? 'h-6' : 'bottom-0' }}">
                                <div class="w-px bg-stone-200 dark:bg-stone-700"></div>
                            </div>
                            <div class="relative flex size-8 flex-none items-center justify-center rounded-full bg-stone-100 dark:bg-stone-800 ring-4 ring-white dark:ring-stone-900">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-stone-500 dark:text-stone-400" aria-hidden="true"><path d="{{ $row->iconPath }}"></path></svg>
                            </div>
                            <div class="flex flex-auto items-start gap-4 py-0.5 pb-6 text-sm/6">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="text-stone-600 dark:text-stone-400">{{ $row->verb }}</span>
                                        @if ($row->otherExists)
                                            <a href="{{ $otherHref($row) }}" class="font-medium text-stone-900 hover:text-stone-700 dark:text-stone-100 dark:hover:text-stone-300">{{ $row->otherLabel }}</a>
                                        @else
                                            <span class="font-medium text-stone-900 dark:text-stone-100">{{ $row->otherLabel }}</span>
                                        @endif
                                        @unless ($row->otherKind->isHelpArticle())
                                            <x-admin.log-id-chip :id="$row->otherId" />
                                        @endunless
                                        @if ($row->listingTitles !== [])
                                            <span class="text-stone-500 dark:text-stone-400">— {{ implode(', ', $row->listingTitles) }}</span>
                                        @endif
                                    </div>
                                    <div class="mt-0.5 flex flex-wrap gap-3 font-mono text-[11px] text-stone-500 dark:text-stone-400">
                                        <span>{{ $row->name }}</span>
                                        <span>ip {{ $row->ip ?? '—' }}</span>
                                        <span>session {{ $row->sessionId ?? '—' }}</span>
                                        @if ($row->requestId !== null)
                                            <a href="{{ route('admin.logs.index', ['request' => $row->requestId]) }}" class="text-stone-500 underline decoration-dotted dark:text-stone-400">request {{ $row->requestId }}</a>
                                        @else
                                            <span>request —</span>
                                        @endif
                                    </div>
                                </div>
                                <time class="flex-none font-mono text-[11px] tabular-nums text-stone-500 dark:text-stone-400" title="{{ $row->occurredAt->format('Y-m-d H:i:s') }} UTC">{{ \App\Domain\Support\RelativeTime::short($row->occurredAt, $now) }}</time>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>
</x-layouts.admin>
