{{-- The dashboard's brand-icon tile (Tailwind Plus "with brand icon"): a
     square icon in the top-left corner, the label and figure beside it,
     the range's line to their right, and a footer strip pinned to the
     bottom. The whole tile is one link, so the footer reads as the call
     to action without being a second target. Takes an
     `App\Seller\OverviewTile`. --}}
@props(['tile'])

<a href="{{ $tile->href }}" class="group relative block overflow-hidden rounded-lg bg-white px-4 pt-5 pb-12 shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:px-6 sm:pt-6 dark:bg-gray-900">
    <span class="absolute rounded-md bg-indigo-500 p-3">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="size-6 text-white">
            <path d="{{ $tile->iconPath }}" />
        </svg>
    </span>

    <p class="ml-16 truncate text-sm font-medium text-gray-500 dark:text-gray-400">{{ $tile->label }}</p>

    <div class="ml-16 flex items-end justify-between gap-3 pb-6 sm:pb-7">
        <p class="flex items-baseline gap-2">
            <span data-tile="{{ $tile->label }}" class="text-2xl font-semibold text-gray-900 tabular-nums dark:text-white">{{ $tile->value }}</span>
            <x-seller.change :text="$tile->changeText" :direction="$tile->changeDirection" />
        </p>

        <x-seller.sparkline :sparkline="$tile->sparkline" class="hidden sm:block" />
    </div>

    <span class="absolute inset-x-0 bottom-0 flex flex-wrap items-baseline gap-x-2 bg-gray-50 px-4 py-4 text-sm sm:px-6 dark:bg-white/5">
        <span class="font-medium text-indigo-600 group-hover:text-indigo-500 dark:text-indigo-400 dark:group-hover:text-indigo-300">{{ $tile->footerLabel }}</span>
        <span class="text-gray-500 dark:text-gray-400">{{ $tile->footerNote }}</span>
    </span>
</a>
