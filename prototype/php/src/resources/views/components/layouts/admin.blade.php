{{-- `full-width` opts a page out of the admin shell's `max-w-6xl` reading
     column — the log viewer's columnar rows earn the extra width; every
     other admin page keeps the narrower default. --}}
@props(['title' => 'Art Store admin', 'fullWidth' => false])

@php
    // The route/label pairs every admin page links to — declared once and
    // rendered twice below (desktop inline nav, mobile menu grid) since the
    // two breakpoints style the same links differently. Messages carries
    // the live unread badge and stays out of this list, rendered by hand
    // in both places the same way it always has.
    $navLinks = [
        ['route' => route('admin.dashboard'), 'label' => 'Dashboard'],
        ['route' => route('admin.sellers.index'), 'label' => 'Sellers'],
        ['route' => route('admin.customers.index'), 'label' => 'Customers'],
        ['route' => route('admin.listings.index'), 'label' => 'Listings'],
        ['route' => route('admin.orders.index'), 'label' => 'Orders'],
        ['route' => route('admin.fulfillments.index'), 'label' => 'Fulfillments'],
        ['route' => route('admin.accounting'), 'label' => 'Accounting'],
        ['route' => route('admin.ledger'), 'label' => 'Ledger'],
        ['route' => route('admin.payouts.index'), 'label' => 'Payouts'],
        ['route' => route('admin.stats'), 'label' => 'Site stats'],
        ['route' => route('admin.logs.index'), 'label' => 'Logs'],
    ];
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
<body class="supports-dark h-full bg-gray-100 dark:bg-gray-950 font-sans text-sm text-gray-900 dark:text-gray-100 antialiased">
    <x-debug-alert />

    <header class="border-b border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">
        <div class="flex items-center gap-3 px-4 py-3 sm:mx-auto sm:max-w-6xl sm:flex-wrap sm:gap-x-6 sm:gap-y-2">
            <a href="{{ route('admin.dashboard') }}" class="font-semibold text-gray-900 dark:text-gray-100">Art Store admin</a>

            @auth('admin')
                {{-- `sm` and up: today's flat inline nav, unchanged. --}}
                <nav aria-label="Admin" class="hidden items-center gap-4 sm:flex">
                    @foreach ($navLinks as $link)
                        <a href="{{ $link['route'] }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">{{ $link['label'] }}</a>
                    @endforeach
                    <a href="{{ route('admin.messages.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100" data-live-badge="Messages" data-events-url="{{ route('admin.events') }}">Messages @if (! empty($unreadMessageCount))({{ $unreadMessageCount }})@endif</a>
                </nav>
            @endauth

            <div class="ml-auto flex items-center gap-4">
                @auth('admin')
                    <span class="hidden text-gray-600 dark:text-gray-400 sm:inline">{{ auth('admin')->user()->displayName() }}</span>

                    <form method="POST" action="{{ route('auth.admin.logout') }}" class="hidden sm:block">
                        @csrf
                        <button type="submit" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">Sign out</button>
                    </form>

                    {{-- Below `sm`: the nav collapses into this JS-free
                         disclosure — same `<details>` popover pattern the
                         logs page's More-filters button already uses, right
                         down to the panel escaping the header via `fixed`
                         so it never has to fight the header row for width. --}}
                    <details class="relative sm:hidden">
                        <summary class="relative z-20 inline-flex min-h-11 cursor-pointer list-none items-center gap-2 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 text-gray-700 dark:text-gray-300 [&::-webkit-details-marker]:hidden">
                            <svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M3 5h14M3 10h14M3 15h14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path></svg>
                            <span>Menu</span>
                        </summary>

                        <div class="fixed inset-x-0 top-16 z-10 max-h-[calc(100vh-4rem)] overflow-y-auto border-b border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-4 py-4 shadow-lg">
                            <nav aria-label="Admin" class="grid grid-cols-2 gap-2">
                                @foreach ($navLinks as $link)
                                    <a href="{{ $link['route'] }}" class="flex min-h-11 items-center rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 text-gray-700 dark:text-gray-300">{{ $link['label'] }}</a>
                                @endforeach
                                <a href="{{ route('admin.messages.index') }}" class="flex min-h-11 items-center rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 text-gray-700 dark:text-gray-300" data-live-badge="Messages" data-events-url="{{ route('admin.events') }}">Messages @if (! empty($unreadMessageCount))({{ $unreadMessageCount }})@endif</a>
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

    <main class="py-6 px-4 {{ $fullWidth ? 'w-full sm:px-6' : 'sm:mx-auto sm:max-w-6xl' }}">
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

    <script defer src="{{ asset('live-badge.js') }}"></script>
</body>
</html>
