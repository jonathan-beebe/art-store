{{-- A list-pane cell's line-1, right-aligned timestamp (DSGN-006): the
     clock for something that happened today, the date otherwise — a bare
     time on an old row would read as if it happened hours ago. --}}
@props(['at'])

<span class="whitespace-nowrap font-mono text-[11px] tabular-nums text-stone-500 dark:text-stone-400">
    {{ $at === null ? '—' : ($at->isToday() ? $at->format('g:ia') : $at->format('M j')) }}
</span>
