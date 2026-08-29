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
            <h3 class="text-sm font-semibold uppercase tracking-wide text-ink-faint">Category tiles <span class="normal-case font-normal">— <code class="font-mono text-xs">shop.partials.category-tiles</code>, live media</span></h3>
            @if ($media === [])
                <p class="mt-3 text-sm text-ink-muted">No attributed medium yet — give a for-sale listing a Medium and the browse row renders here.</p>
            @else
                <div class="mt-4">
                    @include('shop.partials.category-tiles', ['media' => $media, 'activeMedium' => null, 'term' => null])
                </div>
            @endif
        </div>

        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wide text-ink-faint">Category pickers <span class="normal-case font-normal">— exploration: three candidates to replace the tile rows, live media &amp; covers</span></h3>
            @if ($browse === [])
                <p class="mt-3 text-sm text-ink-muted">No attributed medium yet — give a for-sale listing a Medium and the picker candidates render here.</p>
            @else
                <div class="mt-4 space-y-8">
                    <div>
                        <h4 class="text-xs font-semibold text-ink-muted">Five tiles + drawer <span class="font-normal text-ink-faint">— <code class="font-mono text-[11px]">media-tile-row</code>, tint variant · best-stocked five stay out; "All media" unfolds the rest</span></h4>
                        <div class="mt-3">
                            @include('shop.partials.media-tile-row', ['browse' => $browse, 'activeMedium' => null, 'term' => null, 'variant' => 'tint'])
                        </div>
                    </div>

                    <div>
                        <h4 class="text-xs font-semibold text-ink-muted">Cover cards + drawer <span class="font-normal text-ink-faint">— <code class="font-mono text-[11px]">media-tile-row</code>, photo variant · the same row wearing listing covers</span></h4>
                        <div class="mt-3">
                            @include('shop.partials.media-tile-row', ['browse' => $browse, 'activeMedium' => null, 'term' => null, 'variant' => 'photo'])
                        </div>
                    </div>

                    <div>
                        <h4 class="text-xs font-semibold text-ink-muted">Gallery sheet <span class="font-normal text-ink-faint">— <code class="font-mono text-[11px]">media-gallery-panel</code> · zero standing chrome; under 640px it presents as a bottom sheet — see Mobile below</span></h4>
                        <div class="mt-3 min-h-64">
                            @include('shop.partials.media-gallery-panel', ['browse' => $browse, 'activeMedium' => null, 'term' => null])
                        </div>
                    </div>
                </div>
            @endif
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
