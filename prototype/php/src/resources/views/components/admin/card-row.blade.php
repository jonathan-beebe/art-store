{{-- One record's card inside an `x-admin.card-list`: consistent padding
     and stacking so every table's mobile view shares the same rhythm —
     what goes in each line is authored by the caller, since the columns
     that matter differ table to table. --}}
@props([])

<div {{ $attributes->merge(['class' => 'flex flex-col gap-1 p-4']) }}>
    {{ $slot }}
</div>
