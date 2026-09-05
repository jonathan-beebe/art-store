{{--
    A list pane's window footer (DSGN-006 follow-up, mirrored from
    `x-admin.cell-footer`): only renders when the window left rows out, so an
    inbox that fits inside it stays silent. `total` links to the full list
    this window is a slice of.
--}}
@props(['shown', 'total', 'route'])

@if ($total > $shown)
    <p class="shrink-0 border-t border-gray-200 p-3 text-xs text-gray-500 dark:border-white/10 dark:text-gray-500">
        Showing {{ $shown }} of <a href="{{ $route }}" class="underline">{{ $total }}</a>
    </p>
@endif
