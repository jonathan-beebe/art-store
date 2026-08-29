<section id="relationships" class="mt-16 scroll-mt-8" aria-labelledby="relationships-heading">
    <h2 id="relationships-heading" class="font-display text-2xl text-ink">Relationships</h2>
    <p class="mt-2 max-w-2xl text-ink-faint">
        The pairings the palette promises to keep readable, rated live
        against WCAG&nbsp;AA (4.5:1) in both modes. A failing pair turns its
        rating red — and fails the test suite before it ships.
    </p>

    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($pairings as $pair)
            <div class="overflow-hidden rounded-card border border-line">
                <div class="px-5 py-6" style="background: var(--ui-{{ $pair['bg'] }})">
                    <p class="text-lg font-medium" style="color: var(--ui-{{ $pair['fg'] }})">Wheel-thrown, glazed in satin oat white.</p>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-line bg-surface px-4 py-2.5">
                    <span class="text-xs font-semibold text-ink">{{ $pair['fg'] }} <span class="font-normal text-ink-faint">on</span> {{ $pair['bg'] }}</span>
                    <span class="flex gap-1.5 font-mono text-[11px]">
                        <span class="rounded-full px-2 py-0.5 {{ $pair['lightAa'] ? 'bg-success-surface text-success' : 'bg-danger-surface text-danger' }}">light {{ number_format($pair['light'], 1) }}</span>
                        <span class="rounded-full px-2 py-0.5 {{ $pair['darkAa'] ? 'bg-success-surface text-success' : 'bg-danger-surface text-danger' }}">dark {{ number_format($pair['dark'], 1) }}</span>
                    </span>
                </div>
            </div>
        @endforeach
    </div>

    <h3 class="mt-10 text-sm font-semibold uppercase tracking-wide text-ink-faint">Layered together</h3>
    <div class="mt-4 rounded-card border border-line bg-canvas p-8">
        <div class="max-w-md rounded-card border border-line bg-surface p-6">
            <p class="flex items-center gap-2 text-xs text-ink-faint">
                <x-ui.avatar name="Mara" size="xs" /> canvas → surface → line → ink
            </p>
            <h4 class="mt-3 font-display text-lg text-ink">Surface on canvas, ink on surface</h4>
            <p class="mt-2 text-sm text-ink-muted">
                Cards raise <span class="font-semibold">surface</span> off the page's
                <span class="font-semibold">canvas</span> behind a hairline of
                <span class="font-semibold">line</span>; the accent appears once, on the action.
            </p>
            <div class="mt-4 flex items-center gap-3">
                <x-ui.button type="button" variant="primary">Add to cart</x-ui.button>
                <x-ui.badge>One of a kind</x-ui.badge>
            </div>
        </div>
    </div>
</section>
