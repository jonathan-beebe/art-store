@props(['title' => 'Seller portal'])

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="h-full bg-gray-100 font-sans text-sm text-gray-900 antialiased">
    <x-debug-alert />

    <header class="border-b border-gray-300 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3">
            <a href="{{ route('seller.dashboard') }}" class="font-semibold text-gray-900">Art Store seller</a>

            @auth('seller')
                <nav aria-label="Seller portal" class="flex items-center gap-4">
                    <a href="{{ route('seller.dashboard') }}" class="text-gray-600 hover:text-gray-900">Dashboard</a>
                    <a href="{{ route('seller.listings.index') }}" class="text-gray-600 hover:text-gray-900">Listings</a>
                    <a href="{{ route('seller.orders.index') }}" class="text-gray-600 hover:text-gray-900">Orders</a>
                    <a href="{{ route('seller.earnings') }}" class="text-gray-600 hover:text-gray-900">Earnings</a>
                    <a href="{{ route('seller.notifications.index') }}" class="text-gray-600 hover:text-gray-900">Notifications</a>
                    <a href="{{ route('seller.messages.index') }}" class="text-gray-600 hover:text-gray-900" data-live-badge="Messages" data-events-url="{{ route('seller.events') }}">Messages @if (! empty($unreadMessageCount))({{ $unreadMessageCount }})@endif</a>
                    <a href="{{ route('seller.support') }}" class="text-gray-600 hover:text-gray-900">Support</a>
                </nav>
            @endauth

            <div class="ml-auto flex items-center gap-4">
                @auth('seller')
                    <span class="text-gray-600">{{ auth('seller')->user()->displayName() }}</span>

                    <form method="POST" action="{{ route('auth.seller.logout') }}">
                        @csrf
                        <button type="submit" class="text-gray-600 hover:text-gray-900">Sign out</button>
                    </form>
                @else
                    <a href="{{ route('auth.seller.login') }}" class="text-gray-600 hover:text-gray-900">Sign in</a>
                @endauth
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-6">
        @if (session('status'))
            <p role="status" class="mb-4 rounded border border-green-300 bg-green-50 p-3 text-green-900">{{ session('status') }}</p>
        @endif

        @if ($errors->any())
            <div role="alert" class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-red-900">
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
