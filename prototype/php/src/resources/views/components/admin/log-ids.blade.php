{{-- A line's correlation ids that aren't already shown elsewhere in the
     row, as a small disclosure of filter links — only the ids the line
     actually has, minus whichever `exclude` names the row already
     rendered on its own. --}}
@props(['line', 'filters' => [], 'exclude' => []])

@php
    $candidates = [
        'request' => $line->requestId,
        'txn' => $line->txnId,
        'session' => $line->sessionId,
        'actor' => $line->actorId,
    ];
    $ids = collect($candidates)->except($exclude)->filter(fn (?string $id): bool => $id !== null);
@endphp

@if ($ids->isNotEmpty())
    <details class="mt-1">
        <summary class="cursor-pointer text-stone-500">ids</summary>
        <p class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs">
            @foreach ($ids as $param => $id)
                <a href="{{ \App\Logging\Admin\LogFilterLinks::href($param, $id, $filters) }}" class="underline">{{ $id }}</a>
            @endforeach
        </p>
    </details>
@endif
