@props(['title' => 'Seller portal'])

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
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-gray-900 focus:px-4 focus:py-2 focus:font-medium focus:text-white dark:focus:bg-gray-100 dark:focus:text-gray-900">Skip to content</a>

    <x-debug-alert />

    <header class="border-b border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3">
            <a href="{{ route('seller.dashboard') }}" class="font-semibold text-gray-900 dark:text-gray-100">Art Store seller</a>

            @auth('seller')
                <nav aria-label="Seller portal" class="flex items-center gap-4">
                    <a href="{{ route('seller.dashboard') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">Dashboard</a>
                    <a href="{{ route('seller.listings.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">Listings</a>
                    <a href="{{ route('seller.orders.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">Orders</a>
                    <a href="{{ route('seller.earnings') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">Earnings</a>
                    <a href="{{ route('seller.notifications.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">Notifications</a>
                    <a href="{{ route('seller.messages.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">Messages @if (! empty($unreadMessageCount))({{ $unreadMessageCount }})@endif</a>
                    <a href="{{ route('seller.support') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">Support</a>
                </nav>
            @endauth

            <div class="ml-auto flex items-center gap-4">
                @auth('seller')
                    <span class="text-gray-600 dark:text-gray-400">{{ auth('seller')->user()->displayName() }}</span>

                    <form method="POST" action="{{ route('auth.seller.logout') }}">
                        @csrf
                        <button type="submit" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">Sign out</button>
                    </form>
                @else
                    <a href="{{ route('auth.seller.login') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">Sign in</a>
                @endauth
            </div>
        </div>
    </header>

    <main id="main-content" class="mx-auto max-w-6xl px-4 py-6">
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

    <script defer src="{{ asset('configurator-autosubmit.js') }}"></script>
</body>
</html>
