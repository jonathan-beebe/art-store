{{-- A list pane's window footer (DSGN-006 follow-up): only renders when the
     window left rows out, so a section that fits inside it stays silent.
     `total` links to the section's own index — the full list this window is
     a slice of. --}}
@props(['shown', 'total', 'route'])

@if ($total > $shown)
    <p class="shrink-0 border-t border-gray-200 dark:border-gray-800 p-3 text-xs text-gray-500 dark:text-gray-500">
        Showing {{ $shown }} of <a href="{{ $route }}" class="underline">{{ $total }}</a>
    </p>
@endif
