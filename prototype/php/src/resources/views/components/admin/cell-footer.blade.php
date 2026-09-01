{{-- A list pane's window footer (DSGN-006 follow-up): only renders when the
     window left rows out, so a section that fits inside it stays silent.
     `total` links to the section's own index — the full list this window is
     a slice of. --}}
@props(['shown', 'total', 'route'])

@if ($total > $shown)
    <p class="shrink-0 border-t border-stone-200 dark:border-stone-800 p-3 text-xs text-stone-500 dark:text-stone-500">
        Showing {{ $shown }} of <a href="{{ $route }}" class="underline">{{ $total }}</a>
    </p>
@endif
