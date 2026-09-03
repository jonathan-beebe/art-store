{{-- `mode` (DSGN-006) is the one switch that selects both the below-`lg`
     reading column (unchanged from before this ticket) and the `lg`-and-up
     shell shape:
       - 'content'       — one content pane, today's `max-w-6xl` column
                            below `lg` (dashboard, accounting, ledger,
                            payouts).
       - 'content-wide'  — one content pane, today's full-width column
                            below `lg` (logs — the old `full-width: true`).
       - 'list'          — an index page: a list pane plus an empty-detail
                            prompt at `lg`+, the list alone (today's table
                            and cards, untouched) below it.
       - 'detail'         — a show page: a list pane beside the existing
                            detail content at `lg`+; below `lg` the detail
                            content is all that ever showed, unchanged.
     'list' and 'detail' both take a `cells` slot — the `lg`-and-up list
     pane's compact two-line rows, built once per section and passed by
     both its index and its show view so they render the exact same list. --}}
@props(['title' => 'Art Store admin', 'mode' => 'content', 'emptyDetailPrompt' => 'Choose one from the list to see it here.'])

@php
    $admin = auth('admin')->user();

    // The admin nav rail/drawer's tool links, grouped under the section
    // labels a reader scans for — Dashboard sits ungrouped above them since
    // it isn't part of any one workflow. `pattern` drives routeIs() so a
    // link stays active on its section's detail pages too (an order show
    // page keeps Orders active), not just its index route. Each `count` is
    // read from the composer's single-query totals ($navCounts,
    // $unreadMessageCount) — a section with nothing that cheap to show
    // (Accounting, Ledger, Payouts, Logs) carries no count at
    // all rather than a chip worth a real query.
    $navGroups = [
        [
            'label' => null,
            'items' => [
                [
                    'route' => 'admin.dashboard',
                    'pattern' => 'admin.dashboard',
                    'label' => 'Dashboard',
                    'count' => null,
                    'path' => 'm2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
                ],
            ],
        ],
        [
            'label' => 'Marketplace',
            'items' => [
                [
                    'route' => 'admin.orders.index',
                    'pattern' => 'admin.orders.*',
                    'label' => 'Orders',
                    'count' => $navCounts['admin.orders.index'] ?? null,
                    'path' => 'M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007Z',
                ],
                [
                    'route' => 'admin.fulfillments.index',
                    'pattern' => 'admin.fulfillments.*',
                    'label' => 'Fulfillments',
                    'count' => $navCounts['admin.fulfillments.index'] ?? null,
                    'path' => 'M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12',
                ],
                [
                    'route' => 'admin.listings.index',
                    'pattern' => 'admin.listings.*',
                    'label' => 'Listings',
                    'count' => $navCounts['admin.listings.index'] ?? null,
                    'path' => 'm2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A1.5 1.5 0 0 0 21.75 19.5V4.5A1.5 1.5 0 0 0 20.25 3H3.75A1.5 1.5 0 0 0 2.25 4.5v15A1.5 1.5 0 0 0 3.75 21Zm10.5-11.25h.008v.008h-.008V9.75Z',
                ],
                [
                    'route' => 'admin.sellers.index',
                    'pattern' => 'admin.sellers.*',
                    'label' => 'Sellers',
                    'count' => $navCounts['admin.sellers.index'] ?? null,
                    'path' => 'M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.017a3.001 3.001 0 0 0 3.75.617m-16.5 0h16.5m-16.5 0a3 3 0 0 1-.75-1.617v-1.716c0-.216.072-.427.204-.6l1.822-2.416a2.25 2.25 0 0 1 1.799-.9h11.05a2.25 2.25 0 0 1 1.8.9l1.821 2.416c.132.173.204.384.204.6v1.716a3 3 0 0 1-.75 1.617',
                ],
                [
                    'route' => 'admin.customers.index',
                    'pattern' => 'admin.customers.*',
                    'label' => 'Customers',
                    'count' => $navCounts['admin.customers.index'] ?? null,
                    'path' => 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z',
                ],
                [
                    'route' => 'admin.messages.index',
                    'pattern' => 'admin.messages.*',
                    'label' => 'Messages',
                    'count' => $unreadMessageCount ?? null,
                    'path' => 'M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm3.75 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm3.75 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z',
                ],
            ],
        ],
        [
            'label' => 'Money',
            'items' => [
                [
                    'route' => 'admin.accounting',
                    'pattern' => 'admin.accounting',
                    'label' => 'Accounting',
                    'count' => null,
                    'path' => 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
                ],
                [
                    'route' => 'admin.ledger',
                    'pattern' => 'admin.ledger',
                    'label' => 'Ledger',
                    'count' => null,
                    'path' => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z',
                ],
                [
                    'route' => 'admin.payouts.index',
                    'pattern' => 'admin.payouts.*',
                    'label' => 'Payouts',
                    'count' => null,
                    'path' => 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z',
                ],
            ],
        ],
        [
            'label' => 'Observe',
            'items' => [
                [
                    'route' => 'admin.analytics.index',
                    'pattern' => 'admin.analytics.*',
                    'label' => 'Analytics',
                    'count' => null,
                    'path' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125z',
                ],
                [
                    'route' => 'admin.logs.index',
                    'pattern' => 'admin.logs.*',
                    'label' => 'Logs',
                    'count' => null,
                    'path' => 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z',
                ],
            ],
        ],
    ];

    $belowXlMainClasses = $mode === 'content-wide' ? 'w-full sm:px-6' : 'sm:mx-auto sm:max-w-6xl';
    $isPaned = in_array($mode, ['list', 'detail'], true);
    // A full-content page carries no list pane (`$isPaned` is false) — the
    // marker every `assertDontSee('lg:w-[400px]')`-style test now reads
    // instead of an incidental Tailwind class.
    $mainLayoutAttr = $isPaned ? '' : ' data-layout="full"';

    // First letters of up to two words in the admin's name — "Minerva
    // McGonagall" reads as "MM" in the avatar circle.
    $initials = $admin
        ? collect(preg_split('/\s+/', trim($admin->displayName())))
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
    <script defer src="{{ asset('composer.js') }}"></script>
</head>
<body class="supports-dark h-full bg-gray-100 dark:bg-gray-950 font-sans text-sm text-gray-900 dark:text-gray-100 antialiased lg:flex lg:h-screen lg:flex-col lg:overflow-hidden">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-gray-900 focus:px-4 focus:py-2 focus:font-medium focus:text-white dark:focus:bg-gray-100 dark:focus:text-gray-900">Skip to content</a>

    <x-debug-alert />

    @if ($admin)
        {{--
            A unified dark top bar spans every admin screen, with a
            pure-tool left rail underneath it on lg+ and an off-canvas
            drawer holding the same nav below lg. The rail and drawer share
            one nav-items partial so they can't drift. Taupe/stone is the
            admin's one tint difference from the seller portal's indigo.
        --}}
        <header class="sticky top-0 z-40 flex h-16 shrink-0 items-center gap-x-4 border-b border-white/10 bg-stone-900 px-4 dark:bg-stone-950 sm:px-6 lg:px-8">
            <button type="button" data-drawer-open class="-m-2.5 p-2.5 text-stone-400 hover:text-white lg:hidden">
                <span class="sr-only">Open navigation</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" class="size-6">
                    <path d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>

            <a href="{{ route('admin.dashboard') }}" class="flex min-w-0 items-center gap-x-3 font-semibold text-white">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-stone-600 text-sm font-semibold text-white">A</span>
                <span class="truncate">Art Store admin</span>
            </a>

            <div class="ml-auto flex items-center gap-x-4 lg:gap-x-6">
                <span class="flex size-8 items-center justify-center rounded-full bg-white/10 text-xs font-medium text-stone-300 ring-1 ring-white/10">{{ $initials }}</span>

                <form method="POST" action="{{ route('auth.admin.logout') }}" class="hidden lg:block">
                    @csrf
                    <button type="submit" class="text-sm/6 font-semibold text-stone-400 hover:text-white">Sign out</button>
                </form>
            </div>
        </header>

        {{-- Off-canvas drawer (<lg only): a native <dialog>, opened by the
             header's hamburger and closed by its own button, a click on the
             backdrop area, or Escape (native <dialog> behavior — no JS
             needed for that one). --}}
        <dialog id="admin-nav-drawer" class="fixed inset-0 z-50 m-0 h-dvh max-h-none w-full max-w-none bg-transparent p-0 open:flex backdrop:bg-stone-900/80 lg:hidden">
            <div class="flex h-full w-72 max-w-[calc(100%-4rem)] flex-col gap-y-5 overflow-y-auto bg-white px-6 pb-4 dark:bg-stone-900">
                <div class="flex h-16 shrink-0 items-center justify-between">
                    <span class="flex items-center gap-x-3 font-semibold text-gray-900 dark:text-white">
                        <span class="flex size-8 items-center justify-center rounded-lg bg-stone-600 text-sm font-semibold text-white">A</span>
                        Art Store admin
                    </span>
                    <button type="button" data-drawer-close class="-m-2.5 p-2.5 text-gray-500 dark:text-stone-400">
                        <span class="sr-only">Close navigation</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" class="size-6">
                            <path d="M6 18 18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </div>

                <nav aria-label="Admin tools" class="flex flex-1 flex-col">
                    <ul role="list" class="-mx-2 flex-1 space-y-1">
                        @include('components.layouts.partials.admin-nav-items', ['navGroups' => $navGroups])
                    </ul>

                    <div class="mt-auto border-t border-stone-200 pt-4 dark:border-white/10">
                        <form method="POST" action="{{ route('auth.admin.logout') }}">
                            @csrf
                            <button type="submit" class="w-full rounded-md p-2 text-left text-sm/6 font-semibold text-stone-700 hover:bg-stone-50 dark:text-stone-400 dark:hover:bg-white/5">Sign out</button>
                        </form>
                    </div>
                </nav>
            </div>

            <button type="button" data-drawer-close class="flex-1" aria-label="Close navigation"></button>
        </dialog>

        {{-- At `lg` and up: the rail, then the pane(s) — a flex row that
             fills whatever height the header left behind. Below `lg` this
             row is never `flex` at all (no `lg:` prefix reaches it), so it
             stacks in normal document flow exactly as its children's own
             below-`lg` classes already describe. --}}
        <div class="lg:flex lg:min-h-0 lg:flex-1 lg:overflow-hidden">
            {{-- Desktop left rail (lg+ only): pure tool nav, no brand or
                 sign-out — the top bar owns both. --}}
            <nav aria-label="Admin tools" class="hidden lg:flex lg:w-72 lg:shrink-0 lg:flex-col lg:overflow-y-auto lg:border-r lg:border-stone-200 lg:bg-white lg:px-6 lg:py-4 dark:lg:border-white/10 dark:lg:bg-stone-900">
                <ul role="list" class="space-y-1">
                    @include('components.layouts.partials.admin-nav-items', ['navGroups' => $navGroups])
                </ul>
            </nav>

            @if ($isPaned)
                {{-- The list pane: `lg`-and-up only, the section's compact
                     cells (below `lg` the list is `$slot`'s own table/cards,
                     unchanged). Shared between a section's index and show
                     view so both render the exact same list. --}}
                <div data-layout="split" class="hidden lg:flex lg:w-[400px] lg:shrink-0 lg:flex-col lg:overflow-y-auto lg:border-r lg:border-stone-200 lg:bg-white dark:lg:border-white/10 dark:lg:bg-stone-900">
                    {{ $cells ?? '' }}
                </div>
            @endif

            <main id="main-content"{!! $mainLayoutAttr !!} class="py-6 px-4 {{ $belowXlMainClasses }} @if ($mode === 'list') lg:hidden @else lg:flex lg:min-w-0 lg:flex-1 lg:flex-col lg:overflow-y-auto lg:max-w-none lg:mx-0 lg:px-6 lg:py-6 @endif">
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
                {{-- The empty-detail prompt: `lg`-and-up only, shown beside
                     the list pane whenever the index route (rather than a
                     show route) is what put us in 'list' mode. --}}
                <div class="hidden lg:flex lg:min-w-0 lg:flex-1 lg:items-center lg:justify-center lg:overflow-y-auto lg:p-8">
                    <p class="text-stone-500 dark:text-stone-500">{{ $emptyDetailPrompt }}</p>
                </div>
            @endif
        </div>

        <script>
            (() => {
                const drawer = document.getElementById('admin-nav-drawer');
                if (! drawer) return;

                document.querySelectorAll('[data-drawer-open]').forEach((button) => {
                    button.addEventListener('click', () => drawer.showModal());
                });

                drawer.querySelectorAll('[data-drawer-close]').forEach((button) => {
                    button.addEventListener('click', () => drawer.close());
                });
            })();
        </script>
    @else
        <header class="border-b border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3">
                <a href="{{ route('admin.dashboard') }}" class="font-semibold text-gray-900 dark:text-gray-100">Art Store admin</a>

                <div class="ml-auto flex items-center gap-4">
                    <a href="{{ route('auth.admin.login') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">Sign in</a>
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
</body>
</html>
