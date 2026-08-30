{{-- One record's card inside an `x-admin.card-list`: consistent padding
     and stacking so every table's mobile view shares the same rhythm —
     what goes in each line is authored by the caller, since the columns
     that matter differ table to table. An `href` (DSGN-006's list-pane
     cells) makes the whole card a link instead of a plain container —
     every existing caller omits it and keeps the `<div>`. --}}
@props(['href' => null])

@if ($href !== null)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => 'flex flex-col gap-1 p-4 hover:bg-gray-50 dark:hover:bg-gray-800/50']) }}>
        {{ $slot }}
    </a>
@else
    <div {{ $attributes->merge(['class' => 'flex flex-col gap-1 p-4']) }}>
        {{ $slot }}
    </div>
@endif
