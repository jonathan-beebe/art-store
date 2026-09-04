{{-- One request's lines, oldest first, in the columnar layout: time,
     level, event · phase, message (with data/error disclosures and the
     line's own remaining ids), tinted duration. What a `group=1` row
     expands into and the story view's whole body — shared so the two
     never drift. `:headers="true"` renders the story view's column-label
     row above the list; an expanded grouped row already carries its own
     column headers on the collapsed row itself, so it passes `false`.
     `:filters` threads the caller's current filter set into each line's
     `x-admin.log-ids` disclosure, so those links carry it too. --}}
@props(['lines', 'open' => false, 'headers' => false, 'filters' => []])

@php
    $gridCols = 'grid-cols-[92px_64px_200px_minmax(0,1fr)_76px]';
@endphp

<div {{ $attributes->merge(['class' => 'divide-y divide-stone-100 dark:divide-stone-800']) }}>
    @if ($headers)
        <div class="grid {{ $gridCols }} gap-3 items-center px-4 py-2 bg-stone-50 dark:bg-stone-800/50 text-[11px] font-semibold uppercase tracking-wide text-stone-500 dark:text-stone-400">
            <span>Time</span>
            <span>Level</span>
            <span>Event</span>
            <span>Message</span>
            <span class="text-right">Duration</span>
        </div>
    @endif

    @foreach ($lines as $line)
        @php
            $severity = \App\Logging\Admin\LogSeverity::ofLevel($line->level);
            $tint = \App\Logging\Admin\LogDurationTint::ofMs($line->durationMs);
        @endphp
        <div data-line="{{ $line->id }}" data-severity="{{ strtolower($severity->name) }}" class="grid {{ $gridCols }} gap-3 items-start px-4 py-2.5 {{ $severity->rowClasses() !== '' ? $severity->rowClasses() : 'bg-white dark:bg-stone-900' }}">
            <span data-cell="ts" title="{{ $line->ts }}" class="pt-0.5 font-mono text-xs text-stone-500 dark:text-stone-400 tabular-nums">{{ \App\Logging\Admin\LogTimestamp::timeOfDay($line->ts) }}</span>
            <span data-cell="level" class="inline-flex justify-self-start rounded-md bg-stone-100 dark:bg-stone-800 px-1.5 py-0.5 text-[11px] font-semibold text-stone-600 dark:text-stone-300">{{ $line->level ?? '—' }}</span>
            <span data-cell="event" title="{{ $line->event }}{{ $line->phase !== null ? ' · '.$line->phase : '' }}" class="truncate pt-0.5 font-mono text-xs text-stone-600 dark:text-stone-400">{{ $line->event ?? '—' }}@if ($line->phase !== null) &middot; {{ $line->phase }}@endif</span>
            <span data-cell="msg" class="flex flex-col gap-1.5 pt-0.5">
                <span>{{ $line->msg ?? '—' }}</span>
                @if ($line->data !== null)
                    <details @if ($open) open @endif>
                        <summary class="cursor-pointer text-xs text-stone-500 dark:text-stone-400">data</summary>
                        <pre class="mt-1 overflow-x-auto rounded-md bg-stone-50 dark:bg-stone-800/50 p-2 text-xs">{!! \App\Logging\Admin\LogIdLinks::linkify(\App\Logging\Admin\LogJson::pretty($line->data)) !!}</pre>
                    </details>
                @endif
                @if ($line->error !== null)
                    <details @if ($open) open @endif>
                        <summary class="cursor-pointer text-xs text-stone-500 dark:text-stone-400">error</summary>
                        <pre class="mt-1 overflow-x-auto rounded-md bg-stone-50 dark:bg-stone-800/50 p-2 text-xs">{!! \App\Logging\Admin\LogIdLinks::linkify(\App\Logging\Admin\LogJson::pretty($line->error)) !!}</pre>
                    </details>
                @endif
                <x-admin.log-ids :line="$line" :filters="$filters" />
            </span>
            <span data-cell="duration" class="pt-0.5 text-right font-mono text-xs tabular-nums {{ $tint?->textClasses() ?? 'text-stone-400 dark:text-stone-600' }}">{{ $line->durationMs === null ? '—' : $line->durationMs.' ms' }}</span>
        </div>
    @endforeach
</div>
