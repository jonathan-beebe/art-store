@php
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

    $entityPath = $detail->breakdown === \App\Domain\Analytics\EventBreakdown::Actor ? 'actors' : 'listings';
    $entityHref = fn (string $id): string => url('/admin/analytics/'.$entityPath.'/'.$id);

    $barHeights = \App\Domain\Analytics\BarStrip::heights($detail->daily, 112);
    $barTooltips = array_map(
        fn (int $count, string $day): string => \App\Domain\Analytics\AnalyticsRange::dayCaption($day).': '.number_format($count),
        $detail->daily,
        $dayLabels,
    );
@endphp

<x-layouts.admin :title="$detail->label.' — Art Store admin'" mode="content-wide">
    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ $indexHref }}" class="inline-flex items-center gap-1.5 text-stone-600 dark:text-stone-400 hover:text-stone-900 dark:hover:text-stone-100">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M10 4L6 8l4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
            <span>Analytics</span>
        </a>
        <span class="text-stone-400 dark:text-stone-600">/</span>
        <h1 class="mt-1 flex flex-wrap items-center gap-3 text-lg font-semibold text-stone-900 dark:text-stone-100">
            {{ $detail->label }}
            <span class="inline-flex items-center rounded-md bg-stone-100 dark:bg-stone-400/10 px-2 py-0.5 font-mono text-xs font-normal text-stone-700 dark:text-stone-300 inset-ring inset-ring-stone-500/10 dark:inset-ring-stone-400/20">{{ $detail->name }}</span>
        </h1>
        <span class="ml-auto text-stone-600 dark:text-stone-400">{{ $rangeCaption }}</span>
        <div role="group" aria-label="Range" class="flex gap-1">
            @foreach ($rangeLinks as $link)
                <a href="{{ $link['href'] }}" @if ($link['active']) aria-current="true" @endif class="{{ $link['active'] ? $segmentActive : $segmentIdle }}">{{ $link['label'] }}</a>
            @endforeach
        </div>
    </div>

    {{-- tiles --}}
    <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
        @foreach ($detail->tiles as $tile)
            <div class="rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4">
                <dt class="text-stone-600 dark:text-stone-400">{{ $tile->label }}</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums text-stone-900 dark:text-stone-100">{{ $tile->value }}</dd>
                <div class="mt-0.5 text-xs text-stone-500 dark:text-stone-400">{{ $tile->note }}</div>
            </div>
        @endforeach
    </dl>

    {{-- by day --}}
    <section class="mt-6">
        <h2 class="font-semibold text-stone-700 dark:text-stone-300">By day</h2>
        <div class="mt-2 rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4">
            <div class="flex h-28 items-end gap-0.5">
                @foreach ($barHeights as $i => $px)
                    <div title="{{ $barTooltips[$i] }}" style="height: {{ $px }}px" class="min-h-0.5 flex-1 rounded-t-sm bg-stone-400 dark:bg-stone-500"></div>
                @endforeach
            </div>
            <div class="mt-1.5 flex justify-between text-[11px] text-stone-500 dark:text-stone-400">
                <span>{{ $detail->firstDay }}</span>
                <span>{{ $detail->lastDay }}</span>
            </div>
        </div>
    </section>

    {{-- breakdown --}}
    <section aria-labelledby="analytics-breakdown-heading" class="mt-6">
        <div class="flex flex-wrap items-center gap-3">
            <h2 id="analytics-breakdown-heading" class="font-semibold text-stone-700 dark:text-stone-300">{{ $detail->breakdown->heading() }}</h2>
            @if (count($breakdownLinks) > 1)
                <div role="group" aria-label="Breakdown" class="flex gap-1">
                    @foreach ($breakdownLinks as $link)
                        <a href="{{ $link['href'] }}" @if ($link['active']) aria-current="true" @endif class="{{ $link['active'] ? $segmentActive : $segmentIdle }}">{{ $link['label'] }}</a>
                    @endforeach
                </div>
            @endif
        </div>

        @if (empty($detail->rows))
            <x-admin.nothing class="mt-2">No activity in this range.</x-admin.nothing>
        @else
            <div class="mt-2 hidden overflow-x-auto rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 sm:block">
                <table class="w-full text-left">
                    <caption class="sr-only">{{ $detail->breakdown->heading() }}, this range against the range before it</caption>
                    <thead class="border-b border-stone-300 dark:border-stone-700 bg-stone-50 dark:bg-stone-800/50">
                        <tr>
                            <th scope="col" class="px-4 py-2 font-semibold whitespace-nowrap">{{ $detail->breakdown->columnLabel() }}</th>
                            <th scope="col" class="px-4 py-2 font-semibold whitespace-nowrap"></th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold whitespace-nowrap">This range</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold whitespace-nowrap">Previous</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold whitespace-nowrap">Change</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold whitespace-nowrap">Share</th>
                            <th scope="col" class="px-4 py-2 font-semibold whitespace-nowrap"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-200 dark:divide-stone-800">
                        @foreach ($detail->rows as $row)
                            <tr>
                                <th scope="row" class="px-4 py-2 font-normal">
                                    @if ($row->site !== null)
                                        <div class="flex items-center gap-2">
                                            <x-admin.status-badge tint="neutral">{{ $row->site->value }}</x-admin.status-badge>
                                            <span class="font-mono text-xs text-stone-700 dark:text-stone-300">{{ $row->id }}</span>
                                        </div>
                                    @else
                                        <a href="{{ $entityHref($row->id) }}" class="underline">
                                            <x-admin.log-id-chip :id="$row->id" />
                                        </a>
                                    @endif
                                </th>
                                <td class="px-4 py-2 text-stone-600 dark:text-stone-400">
                                    @if ($row->actorKind !== null)
                                        <div class="flex items-center gap-2">
                                            <x-admin.status-badge :tint="$row->actorKind === 'verified' ? 'ok' : 'neutral'">{{ $row->actorKind }}</x-admin.status-badge>
                                            <span>{{ $row->title }}</span>
                                        </div>
                                    @elseif ($row->site === null)
                                        <span>{{ $row->title }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right font-semibold tabular-nums text-stone-900 dark:text-stone-100">{{ number_format($row->current) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums text-stone-600 dark:text-stone-400">{{ number_format($row->previous) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">
                                    <span class="{{ $deltaClass($row->change) }}">{{ $row->change->text }}</span>
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <div class="h-1.5 w-20 overflow-hidden rounded-full bg-stone-100 dark:bg-stone-400/10">
                                            <div class="h-1.5 bg-stone-400 dark:bg-stone-500" style="width: {{ $row->shareWidth }}%"></div>
                                        </div>
                                        <span class="w-9 text-right tabular-nums text-stone-600 dark:text-stone-400">{{ $row->sharePercent }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap">
                                    @if ($row->site === null)
                                        <a href="{{ $entityHref($row->id) }}" class="underline">Events</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-admin.card-list caption="{{ $detail->breakdown->heading() }}, this range against the range before it">
                @foreach ($detail->rows as $row)
                    <x-admin.card-row :href="$row->site === null ? $entityHref($row->id) : null">
                        <div class="flex items-center justify-between gap-3">
                            @if ($row->site !== null)
                                <div class="flex items-center gap-2">
                                    <x-admin.status-badge tint="neutral">{{ $row->site->value }}</x-admin.status-badge>
                                    <span class="font-mono text-xs text-stone-700 dark:text-white">{{ $row->id }}</span>
                                </div>
                            @else
                                <x-admin.log-id-chip :id="$row->id" />
                            @endif
                            <span class="{{ $deltaClass($row->change) }}">{{ $row->change->text }}</span>
                        </div>
                        @if ($row->actorKind !== null)
                            <div class="flex items-center gap-2">
                                <x-admin.status-badge :tint="$row->actorKind === 'verified' ? 'ok' : 'neutral'">{{ $row->actorKind }}</x-admin.status-badge>
                                <span class="text-stone-600 dark:text-stone-400">{{ $row->title }}</span>
                            </div>
                        @elseif ($row->site === null)
                            <span class="text-stone-600 dark:text-stone-400">{{ $row->title }}</span>
                        @endif
                        <div class="flex items-center justify-between text-stone-600 dark:text-stone-400">
                            <span>{{ number_format($row->current) }} this range · {{ number_format($row->previous) }} previous</span>
                            <span>{{ $row->sharePercent }}</span>
                        </div>
                    </x-admin.card-row>
                @endforeach
            </x-admin.card-list>
        @endif
    </section>
</x-layouts.admin>
