@props(['browse', 'categories'])

{{--
    The home page's closing footer (DSGN-007): an answer to "where do I
    start" — search, every medium, every category — rather than a sitemap.
    Renders into the shop layout's `afterMain` slot so its background spans
    full viewport width the way `<header>` already does, with its own
    content centered inside it. `$browse` is `MediumBrowse::forStorefront()`;
    `$categories` is `CategoryBrowse::forStorefront()`.
--}}
<footer class="border-t border-line bg-surface">
    <div class="mx-auto max-w-6xl px-8 py-12">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:gap-6">
            <h2 class="font-display text-xl text-ink">Find your way in</h2>
            <form method="GET" action="{{ route('shop.search') }}" role="search" class="flex w-full gap-2 sm:ml-auto sm:max-w-md">
                <label for="footer-q" class="sr-only">Search the store</label>
                <x-ui.input id="footer-q" type="search" name="q" placeholder="Search for a piece, a maker, a material" class="flex-1" />
                <x-ui.button variant="primary">Search</x-ui.button>
            </form>
        </div>

        @if ($browse !== [] || $categories !== [])
            <div class="mt-8 grid gap-8 sm:grid-cols-2">
                @if ($browse !== [])
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-ink-faint">By medium</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($browse as $medium)
                                <x-ui.chip :href="route('shop.medium', ['medium' => $medium['value']])">{{ $medium['label'] }}</x-ui.chip>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($categories !== [])
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-ink-faint">By category</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($categories as $entry)
                                <x-ui.chip :href="route('shop.browse', ['categoryPath' => $entry['category']->browsePath()])">{{ $entry['category']->name }}</x-ui.chip>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <div class="mt-8 flex flex-col gap-3 border-t border-line pt-6 text-sm text-ink-muted sm:flex-row sm:items-center">
            <p>Every piece is made by hand by the person who sells it.</p>
            <div class="flex gap-4 sm:ml-auto">
                <a href="{{ route('auth.seller.login') }}" class="hover:text-accent">Sell your work</a>
                <a href="{{ route('shop.support') }}" class="hover:text-accent">Support</a>
            </div>
        </div>
    </div>
</footer>
