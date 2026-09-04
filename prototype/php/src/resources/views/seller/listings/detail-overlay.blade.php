{{--
    Table and grid rows open a listing's detail through this one route
    and view (?from=table|grid) — ARCHITECTURE.md § "Overlay vs takeover".
    At `2xl` and up the listings workspace stays visible with the detail
    open over it in a native `<dialog open>`; below that the workspace is
    hidden and the detail takes over the content area with a back link.
    Tailwind's `2xl:` variants pick which markup shows from the one
    response — no JavaScript decides.
--}}
<x-layouts.seller :title="$listing->title.' — Art Store seller'" :bleed="true">
    <x-slot:mobileTitle>{{ $listing->title }}</x-slot:mobileTitle>

    <div class="hidden h-[calc(100dvh-4rem)] flex-col overflow-hidden 2xl:flex">
        @include('seller.listings._header')

        <div class="min-h-0 flex-1 overflow-y-auto p-6 lg:p-8">
            @if ($view === \App\Domain\Seller\ListingView::Table)
                @include('seller.listings._table')
            @else
                @include('seller.listings._grid')
            @endif
        </div>
    </div>

    <dialog open aria-label="{{ $listing->title }}" class="fixed inset-0 z-50 m-0 hidden h-dvh max-h-none w-full max-w-none items-start justify-center overflow-y-auto bg-gray-900/60 p-8 2xl:flex dark:bg-gray-950/70">
        <div class="relative w-full max-w-4xl rounded-xl bg-white p-8 shadow-xl dark:bg-gray-900 dark:outline dark:-outline-offset-1 dark:outline-white/10">
            <a href="{{ $backHref }}" aria-label="Close" class="absolute top-4 right-4 flex size-9 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/10">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-5" aria-hidden="true"><path d="M6 18 18 6M6 6l12 12"></path></svg>
            </a>
            <x-seller.listing-detail :listing="$listing" :row="$row" :sales="$sales" :strip="$strip" :range-days="$rangeDays" />
        </div>
    </dialog>

    <div class="flex h-[calc(100dvh-4rem)] flex-col overflow-hidden 2xl:hidden">
        <div class="min-h-0 flex-1 overflow-y-auto p-6 lg:p-8">
            <a href="{{ $backHref }}" class="mb-4 inline-flex items-center gap-1.5 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M10 4L6 8l4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                <span>Listings</span>
            </a>

            <x-seller.listing-detail :listing="$listing" :row="$row" :sales="$sales" :strip="$strip" :range-days="$rangeDays" />
        </div>
    </div>
</x-layouts.seller>
