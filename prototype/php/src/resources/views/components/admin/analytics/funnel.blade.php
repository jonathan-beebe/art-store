{{-- A shared-borders grid, one cell per FunnelStep: values only, every
     percentage and width already computed by App\Analytics\Admin\Funnel.
     `$funnel` is an App\Analytics\Admin\FunnelView. --}}
@props(['funnel'])

@php
    $deltaClasses = [
        'up' => 'text-green-700 dark:text-green-400',
        'down' => 'text-red-700 dark:text-red-500',
        'flat' => 'text-stone-500 dark:text-stone-400',
    ];
    $deltaClass = fn (\App\Domain\Analytics\RangeChange $change): string => match ($change->direction) {
        \App\Domain\Analytics\ChangeDirection::Up => $deltaClasses['up'],
        \App\Domain\Analytics\ChangeDirection::Down => $deltaClasses['down'],
        \App\Domain\Analytics\ChangeDirection::Flat => $deltaClasses['flat'],
    };

    $columns = min(count($funnel->steps), 7);
    $gridColsClass = match ($columns) {
        1 => 'lg:grid-cols-1',
        2 => 'lg:grid-cols-2',
        3 => 'lg:grid-cols-3',
        4 => 'lg:grid-cols-4',
        5 => 'lg:grid-cols-5',
        6 => 'lg:grid-cols-6',
        default => 'lg:grid-cols-7',
    };

    $footer = function (\App\Analytics\Admin\FunnelStep $step): ?string {
        if ($step->rate === null) {
            return null;
        }

        if ($step->rate->ofLabel === 'visitors') {
            return $step->rate->text.' of '.$step->rate->ofLabel;
        }

        return $step->rate->text.' of '.$step->rate->ofLabel.' · '.$step->shareOfFirst.'% of visitors';
    };
@endphp

<div class="flex items-center justify-end gap-4">
    <span class="inline-flex items-center gap-1.5 text-xs text-stone-500 dark:text-stone-400">
        <span class="inline-block h-2 w-3.5 rounded-full bg-stone-700 dark:bg-stone-400"></span>
        this range
    </span>
    <span class="inline-flex items-center gap-1.5 text-xs text-stone-500 dark:text-stone-400">
        <span class="inline-block h-1 w-3.5 rounded-full bg-stone-300 dark:bg-stone-600"></span>
        previous range
    </span>
</div>

<div {{ $attributes->merge(['class' => "mt-2 grid grid-cols-1 gap-px overflow-hidden rounded-lg bg-stone-200 ring-1 ring-stone-200 sm:grid-cols-3 {$gridColsClass} dark:bg-white/10 dark:ring-white/10"]) }}>
    @foreach ($funnel->steps as $step)
        @php $footerText = $footer($step); @endphp
        <div class="flex min-w-0 flex-col gap-2 bg-white px-6 py-5 dark:bg-stone-900">
            <div class="flex items-center justify-between gap-2">
                <span class="text-sm/6 font-medium whitespace-nowrap text-stone-500 dark:text-stone-400">{{ $step->label }}</span>
                <span class="inline-flex items-center gap-0.5 text-xs font-medium {{ $deltaClass($step->change) }}">
                    @if ($step->change->direction === \App\Domain\Analytics\ChangeDirection::Up)
                        <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3v10M4 7l4-4 4 4"></path></svg>
                    @elseif ($step->change->direction === \App\Domain\Analytics\ChangeDirection::Down)
                        <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 13V3M4 9l4 4 4-4"></path></svg>
                    @endif
                    {{ $step->change->text }}
                </span>
            </div>

            <div class="flex flex-wrap items-baseline gap-2">
                <span class="text-3xl font-semibold tracking-tight tabular-nums text-stone-900 dark:text-white">{{ number_format($step->current) }}</span>
                @if ($step->isLargestDrop)
                    <x-admin.status-badge tint="warn">largest drop</x-admin.status-badge>
                @endif
            </div>

            <div class="flex flex-col gap-0.5" title="this range {{ $step->current }} · previous {{ $step->previous }}">
                <div class="h-2 overflow-hidden rounded-full bg-stone-100 dark:bg-stone-800">
                    <div style="width: {{ $step->shareOfFirst }}%" class="h-2 rounded-full bg-stone-700 dark:bg-stone-400"></div>
                </div>
                <div class="h-1 overflow-hidden rounded-full bg-stone-100 dark:bg-stone-800">
                    <div style="width: {{ $step->previousShareOfFirst }}%" class="h-1 rounded-full bg-stone-300 dark:bg-stone-600"></div>
                </div>
            </div>

            <div class="text-xs text-stone-500 dark:text-stone-400">{{ $footerText ?? '' }}@if ($footerText === null)&nbsp;@endif</div>

            @if ($step->side !== null)
                <div class="mt-1.5 text-xs text-stone-500 dark:text-stone-400">{{ $step->side }}</div>
            @endif

            @if ($step->note !== null)
                <div class="mt-1.5 text-xs text-stone-500 dark:text-stone-400">{{ $step->note }}</div>
            @endif
        </div>
    @endforeach
</div>
