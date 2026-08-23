<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Art Store')</title>
    @vite(['resources/css/app.css'])
</head>
<body class="h-full bg-white text-neutral-900 antialiased">
    @include('partials.debug-alert')

    <header class="border-b border-neutral-100">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-8 gap-y-4 px-8 py-6">
            <a href="{{ route('shop.home') }}" class="text-xs font-medium uppercase tracking-[0.2em] text-neutral-500 hover:text-neutral-900">
                Art Store
            </a>

            <form method="GET" action="{{ route('shop.home') }}" class="order-last w-full sm:order-none sm:w-auto sm:flex-1">
                <label for="site-search" class="sr-only">Search art</label>
                <input id="site-search" type="search" name="q" value="{{ request('q') }}"
                       placeholder="Search art, artists, media"
                       class="w-full rounded-full border border-neutral-200 px-5 py-2 text-base placeholder:text-neutral-400 focus:border-neutral-900 focus:outline-none">
            </form>

            <nav class="flex items-center gap-6 text-sm text-neutral-600">
                <a href="{{ route('shop.favorites') }}" class="hover:text-neutral-900">Favorites</a>
                <a href="{{ route('shop.cart') }}" class="hover:text-neutral-900">Cart @isset($cartItemCount)({{ $cartItemCount }})@endisset</a>
                <a href="{{ route('shop.orders') }}" class="hover:text-neutral-900">Orders</a>

                @auth('customer')
                    <a href="{{ route('shop.account') }}" class="hover:text-neutral-900">
                        Account @if (! empty($unreadNotificationCount))({{ $unreadNotificationCount }})@endif
                    </a>
                @else
                    <a href="{{ route('auth.customer.login') }}" class="hover:text-neutral-900">Sign in</a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-8 pb-24 pt-12">
        @if (session('error') || $errors->any())
            <div role="alert" class="mb-10 rounded-xl border border-red-200 bg-red-50 px-6 py-4 text-red-900">
                @if (session('error'))
                    <p>{{ session('error') }}</p>
                @endif

                @foreach ($errors->all() as $message)
                    <p>{{ $message }}</p>
                @endforeach
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
