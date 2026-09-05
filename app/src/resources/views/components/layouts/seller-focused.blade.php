@props(['listing', 'title' => null])

@php
    use App\Domain\Listings\ListingStatus;
    use App\Configurator\ConfiguratorSectionNav;
    use App\Configurator\PublishIssuePresenter;

    $seller = auth('seller')->user();

    // First letters of up to two words in the shop's display name — matches
    // the unified chrome's own avatar initials (components.layouts.seller)
    // so the two chromes read as one product.
    $initials = $seller
        ? collect(preg_split('/\s+/', trim($seller->displayName())))
            ->filter()
            ->take(2)
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('')
        : '';

    // A published listing has nothing left to publish, and its issues are
    // never computed for it — {@see \App\Configurator\ListingEditPageData}
    // draws the same line.
    $issues = $listing->status === ListingStatus::Draft ? $listing->publishIssues() : [];
    $sections = ConfiguratorSectionNav::sections();
@endphp

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? $listing->title.' — Art Store seller' }}</title>
    @vite(['resources/css/app.css'])
    <x-theme-css />
</head>
<body class="supports-dark h-full bg-gray-100 dark:bg-gray-950 font-sans text-sm text-gray-900 dark:text-gray-100 antialiased">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-gray-900 focus:px-4 focus:py-2 focus:font-medium focus:text-white dark:focus:bg-gray-100 dark:focus:text-gray-900">Skip to content</a>

    <x-debug-alert />

    {{--
        Focused mode (IMPRV-025): the item configurator's own top bar, in
        place of the unified chrome's dark bar + tool rail — a way back, the
        listing being edited, its publish readiness, and the same bell and
        avatar the unified chrome carries, so the two read as one product.
        Below it, a three-column shell (sections / form / buyer preview)
        that stacks on narrow screens rather than scrolling sideways.
    --}}
    <header class="sticky top-0 z-40 flex h-16 shrink-0 items-center gap-x-4 border-b border-white/10 bg-gray-900 px-4 dark:bg-gray-950 sm:px-6 lg:px-8">
        <a href="{{ route('seller.dashboard') }}" class="flex shrink-0 items-center gap-x-3 font-semibold text-white">
            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-indigo-600 text-sm font-semibold text-white">A</span>
            <span class="hidden sm:inline">Art Store seller</span>
        </a>

        <span class="hidden h-6 w-px shrink-0 bg-white/15 sm:block" aria-hidden="true"></span>

        <a href="{{ route('seller.listings.index') }}" class="hidden shrink-0 items-center gap-1 text-sm/6 font-semibold text-gray-400 hover:text-white sm:flex">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" class="size-5">
                <path d="M15.75 19.5 8.25 12l7.5-7.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            Back to listings
        </a>

        <p class="min-w-0 flex-1 truncate text-sm/6 font-semibold text-white">Editing {{ $listing->title }}</p>

        @if ($listing->status === ListingStatus::Draft && $issues !== [])
            <span class="hidden shrink-0 rounded-full bg-red-400/10 px-2 py-1 text-xs font-medium text-red-400 ring-1 ring-inset ring-red-400/20 sm:inline-flex">
                {{ count($issues) }} {{ count($issues) === 1 ? 'issue' : 'issues' }}
            </span>

            <form method="POST" action="{{ route('seller.listings.status', $listing) }}" class="shrink-0">
                @csrf
                <input type="hidden" name="status" value="{{ ListingStatus::ForSale->value }}">
                <button type="submit" disabled title="Fix every issue below before this listing can go live" class="cursor-not-allowed rounded-md bg-indigo-500 px-3 py-1.5 text-sm font-semibold text-white opacity-40 shadow-xs">Publish</button>
            </form>
        @elseif ($listing->status === ListingStatus::Draft)
            <form method="POST" action="{{ route('seller.listings.status', $listing) }}" class="shrink-0">
                @csrf
                <input type="hidden" name="status" value="{{ ListingStatus::ForSale->value }}">
                <button type="submit" class="rounded-md bg-indigo-500 px-3 py-1.5 text-sm font-semibold text-white shadow-xs hover:bg-indigo-400">Publish</button>
            </form>
        @endif

        <a href="{{ route('seller.notifications.index') }}" class="relative -m-2.5 shrink-0 p-2.5 text-gray-400 hover:text-white">
            <span class="sr-only">View notifications</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" class="size-6">
                <path d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            @if ($hasUnreadNotifications ?? false)
                <span class="absolute top-2 right-2 block size-2 rounded-full bg-indigo-400 ring-2 ring-gray-900"></span>
            @endif
        </a>

        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-white/10 text-xs font-medium text-gray-300 ring-1 ring-white/10">{{ $initials }}</span>
    </header>

    <div class="mx-auto flex max-w-7xl flex-col lg:flex-row lg:items-start">
        <nav aria-label="Listing sections" class="w-full shrink-0 overflow-x-auto border-b border-gray-200 px-4 py-3 lg:sticky lg:top-16 lg:flex lg:h-[calc(100dvh-4rem)] lg:w-64 lg:flex-col lg:overflow-y-auto lg:border-r lg:border-b-0 lg:px-6 lg:py-6 dark:border-white/10">
            <ul role="list" class="flex gap-2 lg:flex-col lg:gap-1">
                @foreach ($sections as $item)
                    @php
                        $isCurrent = request()->routeIs($item['pattern']);
                        $sectionHasIssue = ConfiguratorSectionNav::hasIssue($issues, $item['issueCodes']);
                    @endphp
                    <li class="shrink-0 lg:shrink">
                        <a
                            href="{{ route($item['route'], $listing) }}"
                            @if ($isCurrent) aria-current="page" @endif
                            class="flex items-center gap-2 whitespace-nowrap rounded-md px-3 py-2 text-sm font-semibold {{ $isCurrent ? 'bg-gray-50 text-indigo-600 dark:bg-white/5 dark:text-white' : 'text-gray-700 hover:bg-gray-50 hover:text-indigo-600 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white' }}"
                        >
                            {{ $item['label'] }}
                            @if ($sectionHasIssue)
                                <span class="ml-auto inline-flex size-2 shrink-0 rounded-full bg-red-500" aria-hidden="true"></span>
                                <span class="sr-only">— has a publish issue</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>

            @if ($issues !== [])
                <div class="mt-6 hidden flex-col gap-2 border-t border-gray-200 pt-4 lg:flex dark:border-white/10">
                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">Before this can publish</p>
                    @foreach ($issues as $issue)
                        @php $presented = PublishIssuePresenter::present($issue, $listing); @endphp
                        <a href="{{ $presented->fixUrl }}" class="text-xs text-gray-600 underline dark:text-gray-400">{{ $presented->message }}</a>
                    @endforeach
                </div>
            @endif
        </nav>

        <main id="main-content" class="min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <p role="status" class="mb-4 rounded border border-green-300 dark:border-green-900 bg-green-50 dark:bg-green-950/40 p-3 text-green-900 dark:text-green-200">{{ session('status') }}</p>
            @endif

            @if ($errors->any())
                <div role="alert" class="mb-4 rounded border border-red-300 dark:border-red-900 bg-red-50 dark:bg-red-950/40 p-3 text-red-900 dark:text-red-200">
                    @foreach ($errors->all() as $message)
                        <p>{{ $message }}</p>
                    @endforeach
                </div>
            @endif

            <div class="flex flex-col gap-4">
                {{ $slot }}
            </div>
        </main>

        <div class="w-full shrink-0 border-t border-gray-200 px-4 py-6 sm:px-6 lg:w-96 lg:border-t-0 lg:border-l lg:px-6 dark:border-white/10">
            {{-- Every section shows the buyer preview; a page overrides the
                 slot only to pin an input or add a caption beside it. --}}
            <div class="flex flex-col gap-4">
                @isset($preview)
                    {{ $preview }}
                @else
                    <x-seller.buyer-view :listing="$listing" />
                @endisset
            </div>
        </div>
    </div>

    <script defer src="{{ asset('configurator-autosubmit.js') }}"></script>
</body>
</html>
