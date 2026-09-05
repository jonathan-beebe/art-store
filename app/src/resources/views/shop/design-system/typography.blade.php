@php
    use App\Support\DesignTokens;

    $fonts = DesignTokens::fonts();
@endphp

<section id="typography" class="mt-16 scroll-mt-8" aria-labelledby="typography-heading">
    <h2 id="typography-heading" class="font-display text-2xl text-ink">Typography</h2>
    <p class="mt-2 max-w-2xl text-ink-faint">
        Two voices: a serif display face for headings, titles, and the
        wordmark; a humanist sans for everything else.
    </p>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <div class="rounded-card border border-line bg-surface p-6">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-ink-faint">Display — <span class="normal-case">{{ $fonts['display'] }}</span></h3>
            <p class="mt-5 font-display text-4xl leading-tight text-ink">Hand-made art, straight from the artist</p>
            <p class="mt-4 font-display text-2xl text-ink">Thrown stoneware vase</p>
            <p class="mt-3 font-display text-lg text-ink">Questions &amp; answers</p>
        </div>

        <div class="rounded-card border border-line bg-surface p-6">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-ink-faint">Body — <span class="normal-case">{{ $fonts['body'] }}</span></h3>
            <p class="mt-5 text-lg text-ink">Wheel-thrown from local red stoneware and glazed in a satin oat white.</p>
            <p class="mt-3 text-base text-ink-muted">Each one keeps the throwing rings from the wheel, so no two are quite alike.</p>
            <p class="mt-3 text-sm text-ink-faint">Ships in 2–3 days from the studio · Free returns for 14 days</p>
            <p class="mt-5 flex flex-wrap gap-x-6 gap-y-2 text-base text-ink">
                <span class="font-normal">Regular 400</span>
                <span class="font-medium">Medium 500</span>
                <span class="font-semibold">Semibold 600</span>
                <span class="font-bold">Bold 700</span>
            </p>
        </div>
    </div>
</section>
