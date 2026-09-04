@props(['title' => 'Seller portal', 'bleed' => false])

@php
    $seller = auth('seller')->user();

    $navLinks = [
        [
            'route' => 'seller.dashboard',
            'pattern' => 'seller.dashboard',
            'label' => 'Dashboard',
            'count' => null,
            'path' => 'm2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
        ],
        [
            'route' => 'seller.store.show',
            'pattern' => 'seller.store.*',
            'label' => 'Store',
            'count' => null,
            'path' => 'M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 0 0 3.75.615m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z',
        ],
        [
            'route' => 'seller.listings.index',
            'pattern' => 'seller.listings.*',
            'label' => 'Listings',
            'count' => null,
            'path' => 'm2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A1.5 1.5 0 0 0 21.75 19.5V4.5A1.5 1.5 0 0 0 20.25 3H3.75A1.5 1.5 0 0 0 2.25 4.5v15A1.5 1.5 0 0 0 3.75 21Zm10.5-11.25h.008v.008h-.008V9.75Z',
        ],
        [
            'route' => 'seller.orders.index',
            'pattern' => 'seller.orders.*',
            'label' => 'Orders',
            'count' => $awaitingShipmentCount ?? 0,
            'path' => 'M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007Z',
        ],
        [
            'route' => 'seller.customers.index',
            'pattern' => 'seller.customers.*',
            'label' => 'Customers',
            'count' => null,
            'path' => 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z',
        ],
        [
            'route' => 'seller.messages.index',
            'pattern' => 'seller.messages.*',
            'label' => 'Messages',
            'count' => $unreadMessageCount ?? 0,
            'path' => 'M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm3.75 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm3.75 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z',
        ],
        [
            'route' => 'seller.earnings',
            'pattern' => 'seller.earnings',
            'label' => 'Earnings',
            'count' => null,
            'path' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z',
        ],
        [
            'route' => 'seller.support',
            'pattern' => 'seller.support',
            'label' => 'Support',
            'count' => null,
            'path' => 'M16.712 4.33a9.027 9.027 0 0 1 1.652 1.306c.51.51.944 1.064 1.306 1.652M16.712 4.33l-3.448 4.138m3.448-4.138a9.014 9.014 0 0 0-9.424 0M19.67 7.288l-4.138 3.448m4.138-3.448a9.014 9.014 0 0 1 0 9.424m-4.138-5.976a3.736 3.736 0 0 0-.88-1.388 3.737 3.737 0 0 0-1.388-.88m2.268 2.268a3.765 3.765 0 0 1 0 2.528m-2.268-4.796a3.765 3.765 0 0 0-2.528 0m4.796 4.796c-.181.506-.475.982-.88 1.388a3.736 3.736 0 0 1-1.388.88m2.268-2.268 4.138 3.448m0 0a9.027 9.027 0 0 1-1.306 1.652c-.51.51-1.064.944-1.652 1.306m0 0-3.448-4.138m3.448 4.138a9.014 9.014 0 0 1-9.424 0m5.976-4.138a3.765 3.765 0 0 1-2.528 0m0 0a3.736 3.736 0 0 1-1.388-.88 3.737 3.737 0 0 1-.88-1.388m2.268 2.268L7.288 19.67m0 0a9.024 9.024 0 0 1-1.652-1.306 9.027 9.027 0 0 1-1.306-1.652m0 0 4.138-3.448M4.33 16.712a9.014 9.014 0 0 1 0-9.424m4.138 5.976a3.765 3.765 0 0 1 0-2.528m0 0c.181-.506.475-.982.88-1.388a3.736 3.736 0 0 1 1.388-.88m-2.268 2.268L4.33 7.288m6.406 1.18L7.288 4.33m0 0a9.024 9.024 0 0 0-2.958 2.958',
        ],
    ];

    // First letters of up to two words in the shop's display name — "Blue
    // Kiln Studio" reads as "BK" in the avatar circle.
    $initials = $seller
        ? collect(preg_split('/\s+/', trim($seller->displayName())))
            ->filter()
            ->take(2)
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('')
        : '';
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
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-gray-900 focus:px-4 focus:py-2 focus:font-medium focus:text-white dark:focus:bg-gray-100 dark:focus:text-gray-900">Skip to content</a>

    <x-debug-alert />

    @if ($seller)
        {{--
            A unified dark top bar spans every seller screen, with a
            pure-tool left rail underneath it on lg+ and an off-canvas
            drawer holding the same nav below lg. The rail and drawer share
            one nav-items partial so they can't drift.
        --}}
        <header class="sticky top-0 z-40 flex h-16 shrink-0 items-center gap-x-4 border-b border-white/10 bg-gray-900 px-4 dark:bg-gray-950 sm:px-6 lg:px-8">
            <button type="button" data-drawer-open class="-m-2.5 p-2.5 text-gray-400 hover:text-white lg:hidden">
                <span class="sr-only">Open navigation</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" class="size-6">
                    <path d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>

            {{--
                Below `lg`, a page that supplies a `mobileTitle` slot swaps
                this brand cluster out for that title — the bell and avatar
                in the `ml-auto` group stay either way. `lg`+ always keeps
                the brand; nothing here has opted into a wide-screen title
                yet. Slot-driven (not a prop) so a page can pass rich markup
                later, not just a string.
            --}}
            <a href="{{ route('seller.dashboard') }}" class="flex min-w-0 items-center gap-x-3 font-semibold text-white @if (isset($mobileTitle)) max-lg:hidden @endif">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-indigo-600 text-sm font-semibold text-white">A</span>
                <span class="truncate">Art Store seller</span>
            </a>

            @isset($mobileTitle)
                <p class="min-w-0 flex-1 truncate font-semibold text-white lg:hidden">{{ $mobileTitle }}</p>
            @endisset

            <div class="ml-auto flex items-center gap-x-4 lg:gap-x-6">
                <a href="{{ route('seller.notifications.index') }}" class="relative -m-2.5 p-2.5 text-gray-400 hover:text-white">
                    <span class="sr-only">View notifications</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" class="size-6">
                        <path d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    @if ($hasUnreadNotifications ?? false)
                        <span class="absolute top-2 right-2 block size-2 rounded-full bg-indigo-400 ring-2 ring-gray-900"></span>
                    @endif
                </a>

                <span class="flex size-8 items-center justify-center rounded-full bg-white/10 text-xs font-medium text-gray-300 ring-1 ring-white/10">{{ $initials }}</span>

                <form method="POST" action="{{ route('auth.seller.logout') }}" class="hidden lg:block">
                    @csrf
                    <button type="submit" class="text-sm/6 font-semibold text-gray-400 hover:text-white">Sign out</button>
                </form>
            </div>
        </header>

        {{-- Off-canvas drawer (<lg only): a native <dialog>, opened by the
             header's hamburger and closed by its own button, a click on the
             backdrop area, or Escape (native <dialog> behavior — no JS
             needed for that one). --}}
        <dialog id="seller-nav-drawer" data-nav-drawer class="fixed inset-0 z-50 m-0 h-dvh max-h-none w-full max-w-none bg-transparent p-0 open:flex backdrop:bg-gray-900/80 lg:hidden">
            <div class="flex h-full w-72 max-w-[calc(100%-4rem)] flex-col gap-y-5 overflow-y-auto bg-white px-6 pb-4 dark:bg-gray-900">
                <div class="flex h-16 shrink-0 items-center justify-between">
                    <span class="flex items-center gap-x-3 font-semibold text-gray-900 dark:text-white">
                        <span class="flex size-8 items-center justify-center rounded-lg bg-indigo-600 text-sm font-semibold text-white">A</span>
                        Art Store seller
                    </span>
                    <button type="button" data-drawer-close class="-m-2.5 p-2.5 text-gray-500 dark:text-gray-400">
                        <span class="sr-only">Close navigation</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" class="size-6">
                            <path d="M6 18 18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </div>

                <nav aria-label="Seller tools" class="flex flex-1 flex-col">
                    <ul role="list" class="-mx-2 flex-1 space-y-1">
                        @include('components.layouts.partials.seller-nav-items', ['navLinks' => $navLinks])
                    </ul>

                    <div class="mt-auto border-t border-gray-200 pt-4 dark:border-white/10">
                        <form method="POST" action="{{ route('auth.seller.logout') }}">
                            @csrf
                            <button type="submit" class="w-full rounded-md p-2 text-left text-sm/6 font-semibold text-gray-700 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-white/5">Sign out</button>
                        </form>
                    </div>
                </nav>
            </div>

            <button type="button" data-drawer-close class="flex-1" aria-label="Close navigation"></button>
        </dialog>

        <div class="lg:flex lg:items-start">
            {{-- Desktop left rail (lg+ only): pure tool nav, no brand or
                 sign-out — the top bar owns both. --}}
            <nav aria-label="Seller tools" class="hidden lg:sticky lg:top-16 lg:flex lg:h-[calc(100dvh-4rem)] lg:w-72 lg:shrink-0 lg:flex-col lg:overflow-y-auto lg:border-r lg:border-gray-200 lg:bg-white lg:px-6 lg:py-4 dark:lg:border-white/10 dark:lg:bg-gray-900">
                <ul role="list" class="space-y-1">
                    @include('components.layouts.partials.seller-nav-items', ['navLinks' => $navLinks])
                </ul>
            </nav>

            {{-- A bleeding page owns its own edges: list/detail tools fill the
                 viewport under the top bar, so the gutter moves into their rows. --}}
            <main id="main-content" @class(['min-w-0 flex-1', 'px-4 py-6 sm:px-6 lg:px-8' => ! $bleed])>
                <div @class(['px-4 pt-4 sm:px-6 lg:px-8' => $bleed])>
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
                </div>

                {{ $slot }}
            </main>
        </div>

    @else
        <header class="border-b border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3">
                <a href="{{ route('seller.dashboard') }}" class="font-semibold text-gray-900 dark:text-gray-100">Art Store seller</a>

                <div class="ml-auto flex items-center gap-4">
                    <a href="{{ route('auth.seller.login') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">Sign in</a>
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
    @endif

    <script defer src="{{ asset('nav-drawer.js') }}"></script>
    <script defer src="{{ asset('print-button.js') }}"></script>
    <script defer src="{{ asset('configurator-autosubmit.js') }}"></script>
    <script defer src="{{ asset('sort-autosubmit.js') }}"></script>
    <script defer src="{{ asset('composer.js') }}"></script>
</body>
</html>
