@props(['title' => 'Art Store'])

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css'])
    <x-theme-css />
</head>
<body class="supports-dark h-full bg-canvas font-body text-ink antialiased">
    <x-debug-alert />

    <header class="border-b border-line">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-8 gap-y-4 px-8 py-5">
            <a href="{{ route('shop.home') }}" class="font-display text-lg text-ink hover:text-accent">
                Art Store
            </a>

            <form method="GET" action="{{ route('shop.search') }}" class="order-last w-full sm:order-none sm:w-auto sm:flex-1">
                <label for="site-search" class="sr-only">Search art</label>
                <input id="site-search" type="search" name="q" value="{{ request('q') }}"
                       placeholder="Search makers and their work"
                       class="w-full rounded-full border border-line bg-surface px-5 py-2 text-base text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none">
            </form>

            <nav class="flex items-center gap-6 text-sm text-ink-muted">
                <a href="{{ route('shop.favorites') }}" class="hover:text-accent">Favorites</a>
                <a href="{{ route('shop.cart') }}" class="hover:text-accent">Cart @isset($cartItemCount)({{ $cartItemCount }})@endisset</a>
                <a href="{{ route('shop.orders') }}" class="hover:text-accent">Orders</a>
                <a href="{{ route('shop.messages.index') }}" class="hover:text-accent" data-live-badge="Messages" data-events-url="{{ route('shop.events') }}">Messages @if (! empty($unreadMessageCount))({{ $unreadMessageCount }})@endif</a>

                @auth('customer')
                    <a href="{{ route('shop.account') }}" class="hover:text-accent">
                        Account @if (! empty($unreadNotificationCount))({{ $unreadNotificationCount }})@endif
                    </a>
                @else
                    <a href="{{ route('auth.customer.login') }}" class="hover:text-accent">Sign in</a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-8 pb-24 pt-12">
        @if (session('error') || $errors->any())
            <x-ui.alert tone="danger" class="mb-10">
                @if (session('error'))
                    <p>{{ session('error') }}</p>
                @endif

                @foreach ($errors->all() as $message)
                    <p>{{ $message }}</p>
                @endforeach
            </x-ui.alert>
        @endif

        {{ $slot }}
    </main>

    <script defer src="{{ asset('live-badge.js') }}"></script>
    <script defer src="{{ asset('configurator-autosubmit.js') }}"></script>
</body>
</html>
