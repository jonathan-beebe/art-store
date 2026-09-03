@php
    $segmentActive = 'rounded-md px-2.5 py-1 text-xs font-medium bg-stone-900 dark:bg-stone-100 text-white dark:text-stone-900';
    $segmentIdle = 'rounded-md px-2.5 py-1 text-xs font-medium bg-stone-100 dark:bg-stone-400/10 text-stone-600 dark:text-stone-400 hover:bg-stone-200 dark:hover:bg-stone-400/20';

    $deltaClasses = [
        'up' => 'text-green-700 dark:text-green-400 font-medium',
        'down' => 'text-red-700 dark:text-red-500 font-medium',
        'flat' => 'text-stone-500 dark:text-stone-400',
    ];

    $deltaClass = function (\App\Domain\Analytics\RangeChange $change) use ($deltaClasses): string {
        return match ($change->direction) {
            \App\Domain\Analytics\ChangeDirection::Up => $deltaClasses['up'],
            \App\Domain\Analytics\ChangeDirection::Down => $deltaClasses['down'],
            \App\Domain\Analytics\ChangeDirection::Flat => $deltaClasses['flat'],
        };
    };

    $channelHref = fn (string $key): string => route('admin.analytics.channels.show', ['key' => $key, ...$roundTripped]);
@endphp

<x-layouts.admin title="Channels — Art Store admin" mode="content-wide">
    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ $indexHref }}" class="inline-flex items-center gap-1.5 text-stone-600 dark:text-stone-400 hover:text-stone-900 dark:hover:text-stone-100">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M10 4L6 8l4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
            <span>Analytics</span>
        </a>
        <span class="text-stone-400 dark:text-stone-600">/</span>
        <h1 class="mt-1 flex flex-wrap items-center gap-3 text-lg font-semibold text-stone-900 dark:text-stone-100">Channels</h1>
        <span class="ml-auto text-stone-600 dark:text-stone-400">{{ $rangeCaption }}</span>
        <div role="group" aria-label="Range" class="flex gap-1">
            @foreach ($rangeLinks as $link)
                <a href="{{ $link['href'] }}" @if ($link['active']) aria-current="true" @endif class="{{ $link['active'] ? $segmentActive : $segmentIdle }}">{{ $link['label'] }}</a>
            @endforeach
        </div>
    </div>

    @if (empty($channels))
        <x-admin.nothing class="mt-4">No channel activity in this range.</x-admin.nothing>
    @else
        <div class="mt-4 hidden overflow-x-auto rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 sm:block">
            <table class="w-full text-left">
                <caption class="sr-only">Every channel compared against the range before it</caption>
                <thead class="border-b border-stone-300 dark:border-stone-700 bg-stone-50 dark:bg-stone-800/50">
                    <tr>
                        <th scope="col" class="px-4 py-2 font-semibold whitespace-nowrap">Channel</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold whitespace-nowrap">Visitors</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold whitespace-nowrap">Listing views</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold whitespace-nowrap">Cart adds</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold whitespace-nowrap">Orders placed</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold whitespace-nowrap">Orders paid</th>
                        <th scope="col" class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-200 dark:divide-stone-800">
                    @foreach ($channels as $channel)
                        @php $href = $channelHref($channel->channelKey); @endphp
                        <tr class="group relative hover:bg-stone-50 dark:hover:bg-stone-800/50">
                            <th scope="row" class="relative px-4 py-2 font-normal">
                                <a href="{{ $href }}" class="flex flex-col after:absolute after:inset-0 after:content-['']">
                                    <span class="font-medium text-stone-900 hover:text-stone-700 dark:text-stone-100 dark:hover:text-stone-300">{{ $channel->label }}</span>
                                    <span class="font-mono text-[11px] text-stone-500 dark:text-stone-400">{{ $channel->channelKey }}</span>
                                </a>
                            </th>
                            <td class="relative px-4 py-2 text-right tabular-nums">
                                <span class="font-semibold text-stone-900 dark:text-stone-100">{{ number_format($channel->visitors->current) }}</span>
                                <span class="ml-1 {{ $deltaClass($channel->visitors->change) }}">{{ $channel->visitors->change->text }}</span>
                                <x-admin.analytics.stretched-link :href="$href" />
                            </td>
                            <td class="relative px-4 py-2 text-right tabular-nums">
                                <span class="font-semibold text-stone-900 dark:text-stone-100">{{ number_format($channel->views->current) }}</span>
                                <span class="ml-1 {{ $deltaClass($channel->views->change) }}">{{ $channel->views->change->text }}</span>
                                <x-admin.analytics.stretched-link :href="$href" />
                            </td>
                            <td class="relative px-4 py-2 text-right tabular-nums">
                                <span class="font-semibold text-stone-900 dark:text-stone-100">{{ number_format($channel->cartAdds->current) }}</span>
                                <span class="ml-1 {{ $deltaClass($channel->cartAdds->change) }}">{{ $channel->cartAdds->change->text }}</span>
                                <x-admin.analytics.stretched-link :href="$href" />
                            </td>
                            <td class="relative px-4 py-2 text-right tabular-nums">
                                <span class="font-semibold text-stone-900 dark:text-stone-100">{{ number_format($channel->ordersPlaced->current) }}</span>
                                <span class="ml-1 {{ $deltaClass($channel->ordersPlaced->change) }}">{{ $channel->ordersPlaced->change->text }}</span>
                                <x-admin.analytics.stretched-link :href="$href" />
                            </td>
                            <td class="relative px-4 py-2 text-right tabular-nums">
                                <span class="font-semibold text-stone-900 dark:text-stone-100">{{ number_format($channel->ordersPaid->current) }}</span>
                                <span class="ml-1 {{ $deltaClass($channel->ordersPaid->change) }}">{{ $channel->ordersPaid->change->text }}</span>
                                <x-admin.analytics.stretched-link :href="$href" />
                            </td>
                            <td class="relative px-4 py-2 text-stone-400 dark:text-stone-500">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3l5 5-5 5"></path></svg>
                                <x-admin.analytics.stretched-link :href="$href" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-admin.card-list caption="Every channel compared against the range before it">
            @foreach ($channels as $channel)
                <x-admin.card-row href="{{ $channelHref($channel->channelKey) }}">
                    <div class="flex items-center justify-between gap-3">
                        <span class="font-medium text-stone-900 dark:text-white">{{ $channel->label }}</span>
                        <span class="text-stone-900 dark:text-white">{{ number_format($channel->visitors->current) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-stone-600 dark:text-stone-400">
                        <span class="font-mono text-[11px]">{{ $channel->channelKey }}</span>
                        <span class="{{ $deltaClass($channel->visitors->change) }}">{{ $channel->visitors->change->text }}</span>
                    </div>
                    <div class="flex flex-wrap gap-x-3 text-stone-600 dark:text-stone-400">
                        <span>{{ number_format($channel->views->current) }} views</span>
                        <span>{{ number_format($channel->cartAdds->current) }} cart adds</span>
                        <span>{{ number_format($channel->ordersPlaced->current) }} placed</span>
                        <span>{{ number_format($channel->ordersPaid->current) }} paid</span>
                    </div>
                </x-admin.card-row>
            @endforeach
        </x-admin.card-list>
    @endif
</x-layouts.admin>
