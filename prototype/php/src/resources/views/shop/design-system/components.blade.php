<section id="components" class="mt-16 scroll-mt-8" aria-labelledby="components-heading">
    <h2 id="components-heading" class="font-display text-2xl text-ink">Components</h2>
    <p class="mt-2 max-w-2xl text-ink-faint">
        The storefront's composed pieces, rendered by the same partials the
        live pages include — against real listings where the catalog has them.
    </p>

    <div class="mt-8 space-y-10">
        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wide text-ink-faint">Listing card <span class="normal-case font-normal">— <code class="font-mono text-xs">&lt;x-listing-card&gt;</code>, live data</span></h3>
            @if ($listings->isEmpty())
                <p class="mt-3 text-sm text-ink-muted">No for-sale listing yet — seed the catalog and the real cards render here.</p>
            @else
                <ul class="mt-4 grid max-w-4xl grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($listings as $listing)
                        <li><x-listing-card :listing="$listing" /></li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wide text-ink-faint">Category pickers <span class="normal-case font-normal">— the storefront's browse controls, live media &amp; covers</span></h3>
            @if ($browse === [])
                <p class="mt-3 text-sm text-ink-muted">No attributed medium yet — give a for-sale listing a Medium and the picker candidates render here.</p>
            @else
                <div class="mt-4 space-y-8">
                    <div>
                        <h4 class="text-xs font-semibold text-ink-muted">Five tiles + drawer <span class="font-normal text-ink-faint">— <code class="font-mono text-[11px]">media-tile-row</code>, tint variant · best-stocked five stay out; "All media" unfolds the rest</span></h4>
                        <div class="mt-3">
                            @include('shop.partials.media-tile-row', ['browse' => $browse, 'activeMedium' => null, 'variant' => 'tint'])
                        </div>
                    </div>

                    <div>
                        <h4 class="text-xs font-semibold text-ink-muted">Cover cards + drawer <span class="font-normal text-ink-faint">— <code class="font-mono text-[11px]">media-tile-row</code>, photo variant · the same row wearing listing covers</span></h4>
                        <div class="mt-3">
                            @include('shop.partials.media-tile-row', ['browse' => $browse, 'activeMedium' => null, 'variant' => 'photo'])
                        </div>
                    </div>

                    <div>
                        <h4 class="text-xs font-semibold text-ink-muted">Gallery sheet <span class="font-normal text-ink-faint">— <code class="font-mono text-[11px]">media-gallery-panel</code> · zero standing chrome; under 640px it presents as a bottom sheet — see Mobile below</span></h4>
                        <div class="mt-3 min-h-64">
                            @include('shop.partials.media-gallery-panel', ['browse' => $browse, 'activeMedium' => null])
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wide text-ink-faint">Tiles <span class="normal-case font-normal">— <code class="font-mono text-xs">&lt;x-tile&gt;</code>, golden ratio (1.618:1) at any width</span></h3>
            <p class="mt-2 max-w-2xl text-sm text-ink-faint">
                One tile for both browse rows: a photo cover when one exists, a tint fill otherwise — the medium row above and the category grid below both wear it, in and out of a drawer, at the exact same size.
            </p>
            @if ($categories === [])
                <p class="mt-3 text-sm text-ink-muted">No browsable category yet — seed the catalog and its tiles render here.</p>
            @else
                @php $tints = ['bg-tint-1', 'bg-tint-2', 'bg-tint-3', 'bg-tint-4', 'bg-tint-5']; @endphp
                <div class="mt-4 grid max-w-4xl grid-cols-2 gap-4 sm:grid-cols-4">
                    @foreach ($categories as $index => $entry)
                        <x-tile
                            :href="route('shop.browse', ['categoryPath' => $entry['category']->browsePath()])"
                            :label="$entry['category']->name"
                            :count="$entry['count']"
                            :cover-url="$entry['coverUrl']"
                            :tint="$tints[$index % 5]"
                        />
                    @endforeach
                </div>
            @endif
        </div>

        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wide text-ink-faint">Section header <span class="normal-case font-normal">— <code class="font-mono text-xs">&lt;x-ui.section-header&gt;</code></span></h3>
            <div class="mt-4 max-w-2xl space-y-6">
                <x-ui.section-header title="Browse by medium" />
                <x-ui.section-header title="Search results" :link-href="route('shop.search')" link-label="Start a search" />
            </div>
        </div>

        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wide text-ink-faint">Featured band <span class="normal-case font-normal">— <code class="font-mono text-xs">&lt;x-featured-band&gt;</code>, live from <code class="font-mono text-xs">config('storefront.featured')</code></span></h3>
            @if ($featured === null)
                <p class="mt-3 text-sm text-ink-muted">The configured featured subject names nothing for sale right now — the home page shows no band at all, which is the honest degrade this specimen has nothing further to render.</p>
            @else
                <div class="mt-4 overflow-hidden rounded-card border border-line">
                    <x-featured-band :subject="$featured" />
                </div>
            @endif
        </div>

        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wide text-ink-faint">Wayfinding footer <span class="normal-case font-normal">— <code class="font-mono text-xs">&lt;x-wayfinding-footer&gt;</code>, the home page's closing section rather than a sitemap</span></h3>
            <div class="mt-4 overflow-hidden rounded-card border border-line">
                <x-wayfinding-footer :browse="$browse" :categories="$categories" />
            </div>
        </div>

        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wide text-ink-faint">Add to cart <span class="normal-case font-normal">— <code class="font-mono text-xs">shop.partials.add-to-cart-button</code>, preview mode</span></h3>
            <div class="mt-4">
                @include('shop.partials.add-to-cart-button', ['mode' => 'preview', 'listing' => null])
            </div>
        </div>

        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wide text-ink-faint">Configurator <span class="normal-case font-normal">— <code class="font-mono text-xs">shop.partials.configurator</code>, live preview</span></h3>
            @if ($configurable === null)
                <p class="mt-3 text-sm text-ink-muted">No configurable for-sale listing yet — give one options and the live configurator renders here.</p>
            @else
                <p class="mt-3 text-sm text-ink-muted">
                    Rendering “{{ $configurable->title }}” in preview mode: selections
                    round-trip on this page and reprice live, and Add to cart stays inert.
                </p>
                <div class="mt-2 max-w-lg rounded-card border border-line bg-surface px-6 pb-6 pt-2">
                    @include('shop.partials.configurator', [
                        'listing' => $configurable,
                        'configuration' => $configuration,
                        'focusId' => $focusId,
                        'mode' => 'preview',
                        'refreshUrl' => route('shop.design-system'),
                    ])
                </div>
            @endif
        </div>
    </div>
</section>
