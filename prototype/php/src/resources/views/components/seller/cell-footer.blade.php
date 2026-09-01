{{-- A list pane's window footer (DSGN-006, mirroring x-admin.cell-footer):
     only renders when the window left rows out, so a seller with a normal
     number of listings never sees it. --}}
@props(['shown', 'total'])

@if ($total > $shown)
    <p class="shrink-0 border-t border-gray-200 dark:border-white/10 p-3 text-xs text-gray-500 dark:text-gray-500">
        Showing {{ $shown }} of <a href="{{ route('seller.listings.index') }}" class="underline">{{ $total }}</a>
    </p>
@endif
