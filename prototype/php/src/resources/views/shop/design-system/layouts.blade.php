<section id="layouts" class="mt-16 scroll-mt-8" aria-labelledby="layouts-heading">
    <h2 id="layouts-heading" class="font-display text-2xl text-ink">Layouts</h2>
    <p class="mt-2 max-w-2xl text-ink-faint">
        The two page shapes the storefront composes everything into. The
        header above this page is the real one — every shop view renders
        inside it.
    </p>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <div class="rounded-card border border-line bg-surface p-6">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-ink-faint">Storefront — browse</h3>
            <div class="mt-4 rounded-field border border-line bg-canvas p-4" aria-hidden="true">
                <div class="h-6 rounded-sm border-b border-line bg-surface"></div>
                <div class="mt-3 grid grid-cols-6 gap-1.5">
                    <div class="h-8 rounded-sm border border-line-strong bg-surface"></div>
                    <div class="h-8 rounded-sm bg-tint-1"></div>
                    <div class="h-8 rounded-sm bg-tint-2"></div>
                    <div class="h-8 rounded-sm bg-tint-3"></div>
                    <div class="h-8 rounded-sm bg-tint-4"></div>
                    <div class="h-8 rounded-sm bg-tint-5"></div>
                </div>
                <div class="mt-3 grid grid-cols-3 gap-2">
                    @foreach (range(1, 6) as $cell)
                        <div class="rounded-sm border border-line bg-surface p-1.5">
                            <div class="aspect-square rounded-sm bg-tint-{{ ($cell % 5) + 1 }} opacity-60"></div>
                            <div class="mt-1.5 h-1.5 w-2/3 rounded-sm bg-line"></div>
                            <div class="mt-1 h-1.5 w-1/3 rounded-sm bg-line"></div>
                        </div>
                    @endforeach
                </div>
            </div>
            <p class="mt-3 text-xs text-ink-faint">Header · tile row · card grid (1-up → 2-up → 3-up as the viewport grows).</p>
        </div>

        <div class="rounded-card border border-line bg-surface p-6">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-ink-faint">Listing — detail</h3>
            <div class="mt-4 rounded-field border border-line bg-canvas p-4" aria-hidden="true">
                <div class="h-6 rounded-sm border-b border-line bg-surface"></div>
                <div class="mt-3 grid grid-cols-2 gap-3">
                    <div>
                        <div class="aspect-square rounded-sm bg-tint-3 opacity-60"></div>
                        <div class="mt-1.5 grid grid-cols-4 gap-1">
                            @foreach (range(1, 4) as $thumb)
                                <div class="aspect-square rounded-sm bg-tint-{{ $thumb }} opacity-40"></div>
                            @endforeach
                        </div>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <div class="h-3 w-3/4 rounded-sm bg-line-strong"></div>
                        <div class="h-2 w-1/4 rounded-sm bg-line"></div>
                        <div class="mt-1 h-8 rounded-sm border border-line bg-surface"></div>
                        <div class="h-1.5 w-full rounded-sm bg-line"></div>
                        <div class="h-1.5 w-5/6 rounded-sm bg-line"></div>
                        <div class="mt-auto h-5 w-1/2 rounded-full bg-accent opacity-80"></div>
                    </div>
                </div>
            </div>
            <p class="mt-3 text-xs text-ink-faint">Two columns on desktop, stacked on phones: images lead, the maker card and action follow.</p>
        </div>
    </div>
</section>
