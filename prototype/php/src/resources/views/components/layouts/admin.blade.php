{{-- `mode` (DSGN-006) is the one switch that selects both the below-`xl`
     reading column (unchanged from before this ticket) and the `xl`-and-up
     shell shape — it retires the old boolean `full-width` prop rather than
     sitting beside it as a second mechanism:
       - 'content'       — one content pane, today's `max-w-6xl` column
                            below `xl` (dashboard, accounting, ledger,
                            payouts, stats).
       - 'content-wide'  — one content pane, today's full-width column
                            below `xl` (logs — the old `full-width: true`).
       - 'list'          — an index page: a list pane plus an empty-detail
                            prompt at `xl`+, the list alone (today's table
                            and cards, untouched) below it.
       - 'detail'         — a show page: a list pane beside the existing
                            detail content at `xl`+; below `xl` the detail
                            content is all that ever showed, unchanged.
     'list' and 'detail' both take a `cells` slot — the `xl`-and-up list
     pane's compact two-line rows, built once per section and passed by
     both its index and its show view so they render the exact same list. --}}
@props(['title' => 'Art Store admin', 'mode' => 'content', 'emptyDetailPrompt' => 'Choose one from the list to see it here.'])

@php
    // The route/label/pattern triples every admin page links to — declared
    // once and rendered twice below (the `xl`+ rail, and the below-`xl`
    // header's inline nav and Menu disclosure) since the breakpoints style
    // the same links differently. `pattern` drives `routeIs()` so a link
    // stays active on its section's detail pages too (an order show page
    // keeps Orders active), not just its index route. Messages carries the
    // live unread badge and stays out of this list, rendered by hand
    // everywhere the same way it always has — its own active check lives
    // in `$messagesActive` below so it gets the same treatment without
    // forcing badge markup into the shared loop.
    $navLinks = [
        ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'pattern' => 'admin.dashboard'],
        ['route' => 'admin.sellers.index', 'label' => 'Sellers', 'pattern' => 'admin.sellers.*'],
        ['route' => 'admin.customers.index', 'label' => 'Customers', 'pattern' => 'admin.customers.*'],
        ['route' => 'admin.listings.index', 'label' => 'Listings', 'pattern' => 'admin.listings.*'],
        ['route' => 'admin.orders.index', 'label' => 'Orders', 'pattern' => 'admin.orders.*'],
        ['route' => 'admin.fulfillments.index', 'label' => 'Fulfillments', 'pattern' => 'admin.fulfillments.*'],
        ['route' => 'admin.accounting', 'label' => 'Accounting', 'pattern' => 'admin.accounting'],
        ['route' => 'admin.ledger', 'label' => 'Ledger', 'pattern' => 'admin.ledger'],
        ['route' => 'admin.payouts.index', 'label' => 'Payouts', 'pattern' => 'admin.payouts.*'],
        ['route' => 'admin.stats', 'label' => 'Site stats', 'pattern' => 'admin.stats'],
        ['route' => 'admin.logs.index', 'label' => 'Logs', 'pattern' => 'admin.logs.*'],
    ];
    $messagesActive = request()->routeIs('admin.messages.*');
    $belowXlMainClasses = $mode === 'content-wide' ? 'w-full sm:px-6' : 'sm:mx-auto sm:max-w-6xl';
    $isPaned = in_array($mode, ['list', 'detail'], true);
@endphp

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css'])
    <x-theme-css />
</head>
<body class="supports-dark h-full bg-gray-100 dark:bg-gray-950 font-sans text-sm text-gray-900 dark:text-gray-100 antialiased xl:flex xl:h-screen xl:flex-col xl:overflow-hidden">
    <x-debug-alert />

    {{-- Below `xl`: today's header, untouched — brand, inline nav / Menu
         disclosure, sign-out. At `xl` and up it is `xl:hidden` in full: the
         brand, nav, and sign-out it carried move into the rail below, so
         nothing inside this element needed to change to keep it pixel
         identical below `xl`. --}}
    <header class="border-b border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 xl:hidden">
        <div class="flex items-center gap-3 px-4 py-3 xl:gap-x-6">
            <a href="{{ route('admin.dashboard') }}" class="font-semibold text-gray-900 dark:text-gray-100">Art Store admin</a>

            @auth('admin')
                <nav aria-label="Admin" class="hidden items-center gap-4 xl:flex">
                    @foreach ($navLinks as $link)
                        @php($isActive = request()->routeIs($link['pattern']))
                        <a
                            href="{{ route($link['route']) }}"
                            @if ($isActive) aria-current="page" @endif
                            class="whitespace-nowrap {{ $isActive ? 'border-b-2 border-gray-900 dark:border-gray-100 font-medium text-gray-900 dark:text-gray-100' : 'border-b-2 border-transparent text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100' }}"
                        >{{ $link['label'] }}</a>
                    @endforeach
                    <a
                        href="{{ route('admin.messages.index') }}"
                        @if ($messagesActive) aria-current="page" @endif
                        class="whitespace-nowrap {{ $messagesActive ? 'border-b-2 border-gray-900 dark:border-gray-100 font-medium text-gray-900 dark:text-gray-100' : 'border-b-2 border-transparent text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100' }}"
                        data-live-badge="Messages" data-events-url="{{ route('admin.events') }}"
                    >Messages @if (! empty($unreadMessageCount))({{ $unreadMessageCount }})@endif</a>
                </nav>
            @endauth

            <div class="ml-auto flex items-center gap-4">
                @auth('admin')
                    <span class="hidden text-gray-600 dark:text-gray-400 2xl:inline">{{ auth('admin')->user()->displayName() }}</span>

                    <form method="POST" action="{{ route('auth.admin.logout') }}" class="hidden xl:block">
                        @csrf
                        <button type="submit" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">Sign out</button>
                    </form>

                    <details class="relative xl:hidden">
                        <summary class="relative z-20 inline-flex min-h-11 cursor-pointer list-none items-center gap-2 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 text-gray-700 dark:text-gray-300 [&::-webkit-details-marker]:hidden">
                            <svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M3 5h14M3 10h14M3 15h14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path></svg>
                            <span>Menu</span>
                        </summary>

                        <div class="fixed inset-x-0 top-16 z-10 max-h-[calc(100vh-4rem)] overflow-y-auto border-b border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-4 py-4 shadow-lg">
                            <nav aria-label="Admin" class="grid grid-cols-2 gap-2">
                                @foreach ($navLinks as $link)
                                    @php($isActive = request()->routeIs($link['pattern']))
                                    <a
                                        href="{{ route($link['route']) }}"
                                        @if ($isActive) aria-current="page" @endif
                                        class="flex min-h-11 items-center rounded border px-3 {{ $isActive ? 'border-gray-900 dark:border-gray-100 bg-gray-900 dark:bg-gray-100 font-medium text-white dark:text-gray-900' : 'border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300' }}"
                                    >{{ $link['label'] }}</a>
                                @endforeach
                                <a
                                    href="{{ route('admin.messages.index') }}"
                                    @if ($messagesActive) aria-current="page" @endif
                                    class="flex min-h-11 items-center rounded border px-3 {{ $messagesActive ? 'border-gray-900 dark:border-gray-100 bg-gray-900 dark:bg-gray-100 font-medium text-white dark:text-gray-900' : 'border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300' }}"
                                    data-live-badge="Messages" data-events-url="{{ route('admin.events') }}"
                                >Messages @if (! empty($unreadMessageCount))({{ $unreadMessageCount }})@endif</a>
                            </nav>

                            <div class="mt-3 flex items-center gap-3 border-t border-gray-200 dark:border-gray-800 pt-3 text-gray-600 dark:text-gray-400">
                                <span>{{ auth('admin')->user()->displayName() }}</span>

                                <form method="POST" action="{{ route('auth.admin.logout') }}" class="ml-auto">
                                    @csrf
                                    <button type="submit" class="inline-flex min-h-11 items-center rounded border border-gray-300 dark:border-gray-700 px-3 text-gray-700 dark:text-gray-300">Sign out</button>
                                </form>
                            </div>
                        </div>
                    </details>
                @else
                    <a href="{{ route('auth.admin.login') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">Sign in</a>
                @endauth
            </div>
        </div>
    </header>

    {{-- At `xl` and up: the rail, then the pane(s) — a flex row that fills
         whatever height the header left behind (none; it is `xl:hidden`).
         Below `xl` this row is never `flex` at all (no `xl:` prefix reaches
         it), so it stacks in normal document flow exactly as its children's
         own below-`xl` classes already describe. --}}
    <div class="xl:flex xl:min-h-0 xl:flex-1 xl:overflow-hidden">
        @auth('admin')
            {{-- The rail: brand, the same twelve section links (vertical,
                 the active one filled), user + sign-out pinned to the
                 bottom. `xl`-and-up only — nothing here has a below-`xl`
                 counterpart to stay identical to, since the whole rail is
                 new. --}}
            <div class="hidden xl:flex xl:w-52 xl:shrink-0 xl:flex-col xl:overflow-y-auto xl:border-r xl:border-gray-300 xl:bg-white xl:px-2.5 xl:py-3.5 dark:xl:border-gray-700 dark:xl:bg-gray-900">
                <a href="{{ route('admin.dashboard') }}" class="px-2.5 font-semibold text-gray-900 dark:text-gray-100">Art Store admin</a>

                <nav aria-label="Admin" class="mt-3.5 flex flex-col gap-0.5">
                    @foreach ($navLinks as $link)
                        @php($isActive = request()->routeIs($link['pattern']))
                        <a
                            href="{{ route($link['route']) }}"
                            @if ($isActive) aria-current="page" @endif
                            class="flex min-h-9 items-center justify-between gap-2 rounded px-2.5 {{ $isActive ? 'bg-gray-100 font-medium text-gray-900 dark:bg-gray-800 dark:text-gray-100' : 'text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800/60' }}"
                        >
                            <span>{{ $link['label'] }}</span>
                            @if (isset($navCounts[$link['route']]))
                                <span class="text-xs text-gray-400 dark:text-gray-600">{{ $navCounts[$link['route']] }}</span>
                            @endif
                        </a>
                    @endforeach
                    <a
                        href="{{ route('admin.messages.index') }}"
                        @if ($messagesActive) aria-current="page" @endif
                        class="flex min-h-9 items-center justify-between gap-2 rounded px-2.5 {{ $messagesActive ? 'bg-gray-100 font-medium text-gray-900 dark:bg-gray-800 dark:text-gray-100' : 'text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800/60' }}"
                        data-live-badge="Messages" data-events-url="{{ route('admin.events') }}"
                    ><span>Messages @if (! empty($unreadMessageCount))({{ $unreadMessageCount }})@endif</span></a>
                </nav>

                <div class="mt-auto flex flex-col gap-1.5 border-t border-gray-200 dark:border-gray-800 px-2.5 pt-3">
                    <span class="text-gray-600 dark:text-gray-400">{{ auth('admin')->user()->displayName() }}</span>
                    <form method="POST" action="{{ route('auth.admin.logout') }}">
                        @csrf
                        <button type="submit" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">Sign out</button>
                    </form>
                </div>
            </div>
        @endauth

        @if ($isPaned)
            {{-- The list pane: `xl`-and-up only, the section's compact
                 cells (below `xl` the list is `$slot`'s own table/cards,
                 unchanged). Shared between a section's index and show view
                 so both render the exact same list. --}}
            <div class="hidden xl:flex xl:w-[400px] xl:shrink-0 xl:flex-col xl:overflow-y-auto xl:border-r xl:border-gray-300 xl:bg-white dark:xl:border-gray-700 dark:xl:bg-gray-900">
                {{ $cells ?? '' }}
            </div>
        @endif

        <main class="py-6 px-4 {{ $belowXlMainClasses }} @if ($mode === 'list') xl:hidden @else xl:flex xl:min-w-0 xl:flex-1 xl:flex-col xl:overflow-y-auto xl:max-w-none xl:mx-0 xl:px-6 xl:py-6 @endif">
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

            {{ $slot }}
        </main>

        @if ($mode === 'list')
            {{-- The empty-detail prompt: `xl`-and-up only, shown beside the
                 list pane whenever the index route (rather than a show
                 route) is what put us in 'list' mode. --}}
            <div class="hidden xl:flex xl:min-w-0 xl:flex-1 xl:items-center xl:justify-center xl:overflow-y-auto xl:p-8">
                <p class="text-gray-500 dark:text-gray-500">{{ $emptyDetailPrompt }}</p>
            </div>
        @endif
    </div>

    <script defer src="{{ asset('live-badge.js') }}"></script>
</body>
</html>
