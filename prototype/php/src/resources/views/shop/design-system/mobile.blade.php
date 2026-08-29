<section id="mobile" class="mt-16 scroll-mt-8" aria-labelledby="mobile-heading">
    <h2 id="mobile-heading" class="font-display text-2xl text-ink">Mobile</h2>
    <p class="mt-2 max-w-2xl text-ink-faint">
        One theme, two presentations: the tokens never fork by screen size —
        layout does. Each frame below is a real 390px viewport (an iframe),
        so media queries and fixed positioning inside it behave exactly as
        on a phone. Everything is live: real components, real catalog data.
    </p>

    <dl class="mt-6 grid max-w-2xl gap-x-8 gap-y-2 rounded-card border border-line bg-surface p-5 text-sm sm:grid-cols-[auto_1fr]">
        <dt class="font-semibold text-ink">Under 640px</dt>
        <dd class="text-ink-muted">2-up grid, sheet pickers, sticky buy bar, swipe galleries</dd>
        <dt class="font-semibold text-ink">640–1024px</dt>
        <dd class="text-ink-muted">3-up grid, anchored panels and drawers</dd>
        <dt class="font-semibold text-ink">1024px and up</dt>
        <dd class="text-ink-muted">Desktop: full header, two-column listing page</dd>
    </dl>

    <div class="mt-8 grid gap-8 lg:grid-cols-2">
        <div>
            <h3 class="text-xs font-semibold text-ink-muted">Browse media as a bottom sheet <span class="font-normal text-ink-faint">— <code class="font-mono text-[11px]">media-gallery-panel</code>; tap the pill inside the frame</span></h3>
            <div class="mt-3 w-fit overflow-hidden rounded-[1.75rem] border-4 border-line-strong bg-surface p-1">
                <iframe src="{{ route('shop.design-system.specimen', 'browse-sheet') }}" title="Browse sheet specimen at phone width"
                        class="h-[620px] w-[390px] rounded-[1.4rem] border-0 bg-canvas"></iframe>
            </div>
        </div>

        <div>
            <h3 class="text-xs font-semibold text-ink-muted">Cover-card rail <span class="font-normal text-ink-faint">— <code class="font-mono text-[11px]">media-cover-rail</code>; one row, flick to browse, snap to seat</span></h3>
            <div class="mt-3 w-fit overflow-hidden rounded-[1.75rem] border-4 border-line-strong bg-surface p-1">
                <iframe src="{{ route('shop.design-system.specimen', 'cover-rail') }}" title="Cover rail specimen at phone width"
                        class="h-[620px] w-[390px] rounded-[1.4rem] border-0 bg-canvas"></iframe>
            </div>
        </div>

        <div>
            <h3 class="text-xs font-semibold text-ink-muted">Sticky buy bar <span class="font-normal text-ink-faint">— listing price and action pinned to the bottom edge; scroll the frame</span></h3>
            <div class="mt-3 w-fit overflow-hidden rounded-[1.75rem] border-4 border-line-strong bg-surface p-1">
                <iframe src="{{ route('shop.design-system.specimen', 'buy-bar') }}" title="Buy bar specimen at phone width"
                        class="h-[620px] w-[390px] rounded-[1.4rem] border-0 bg-canvas"></iframe>
            </div>
        </div>

        <div>
            <h3 class="text-xs font-semibold text-ink-muted">Swipe gallery <span class="font-normal text-ink-faint">— listing photos as a scroll-snap carousel; swipe inside the frame</span></h3>
            <div class="mt-3 w-fit overflow-hidden rounded-[1.75rem] border-4 border-line-strong bg-surface p-1">
                <iframe src="{{ route('shop.design-system.specimen', 'swipe-gallery') }}" title="Swipe gallery specimen at phone width"
                        class="h-[620px] w-[390px] rounded-[1.4rem] border-0 bg-canvas"></iframe>
            </div>
        </div>
    </div>
</section>
