{{-- A row of tiles reading a funnel, first to last: each tile's own bar
     is a share of the first step's count, so the row narrows the way a
     funnel does. `$funnel` is an App\Analytics\Admin\FunnelView. --}}
@props(['funnel'])

@php
    $deltaClasses = [
        'up' => 'text-green-700 dark:text-green-400 font-medium',
        'down' => 'text-red-700 dark:text-red-500 font-medium',
        'flat' => 'text-stone-500 dark:text-stone-400',
    ];
    $deltaClass = fn (\App\Domain\Analytics\RangeChange $change): string => match ($change->direction) {
        \App\Domain\Analytics\ChangeDirection::Up => $deltaClasses['up'],
        \App\Domain\Analytics\ChangeDirection::Down => $deltaClasses['down'],
        \App\Domain\Analytics\ChangeDirection::Flat => $deltaClasses['flat'],
    };
@endphp

<dl {{ $attributes->merge(['class' => 'mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7']) }}>
    @foreach ($funnel->steps as $step)
        <div class="rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4">
            <dt class="text-stone-600 dark:text-stone-400">{{ $step->label }}</dt>
            <dd class="mt-1 text-2xl font-semibold tabular-nums text-stone-900 dark:text-stone-100">{{ number_format($step->current) }}</dd>

            <div class="mt-1 text-xs whitespace-nowrap text-stone-500 dark:text-stone-400">
                @if ($step->rate !== null)
                    {{ $step->rate->text }} of {{ $step->rate->ofLabel }}
                @else
                    &nbsp;
                @endif
            </div>

            <div class="text-xs">
                <span class="{{ $deltaClass($step->change) }}">{{ $step->change->text }}</span>
                <span class="text-stone-500 dark:text-stone-400">vs previous range</span>
            </div>

            @if ($step->note !== null)
                <div class="mt-0.5 text-xs text-stone-500 dark:text-stone-400">{{ $step->note }}</div>
            @endif

            <div class="mt-2 h-1.5 w-full rounded-full bg-stone-100 dark:bg-stone-800">
                <div style="width: {{ $step->shareOfFirst }}%" class="h-1.5 rounded-full bg-stone-500 dark:bg-stone-400"></div>
            </div>
        </div>
    @endforeach
</dl>
