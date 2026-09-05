@php
    use App\Support\DesignTokens;

    // Chip strips like a hardware store's paint cards: the bands are pure
    // color (inline `var(--ui-*)`, so they follow the live theme and flip
    // with dark mode); names, values, and roles sit beside them where
    // contrast is guaranteed.
    $strips = [
        'Neutrals' => ['group' => 'neutral', 'note' => 'The room the art hangs in — page, cards, text, and lines.'],
        'Accent' => ['group' => 'accent', 'note' => 'Terracotta: the one color the chrome may speak in.'],
        'Status' => ['group' => 'status', 'note' => 'Errors, confirmations, and holds, warm-leaning to sit in the palette.'],
        'Tints' => ['group' => 'tint', 'note' => 'Object colors for tiles and avatars — the same in both modes.'],
    ];
@endphp

<section id="colors" class="mt-16 scroll-mt-8" aria-labelledby="colors-heading">
    <h2 id="colors-heading" class="font-display text-2xl text-ink">Colors</h2>
    <p class="mt-2 max-w-2xl text-ink-faint">
        Every color is a semantic role — views say <code class="font-mono text-xs">bg-canvas</code> or
        <code class="font-mono text-xs">text-ink-muted</code>, never a hex. Each band shows its live value;
        the printed pair is light&nbsp;·&nbsp;dark.
    </p>

    <div class="mt-8 grid gap-6 md:grid-cols-2">
        @foreach ($strips as $stripName => $strip)
            <div class="overflow-hidden rounded-card border border-line bg-surface">
                <div class="border-b border-line px-5 py-3">
                    <h3 class="text-sm font-semibold text-ink">{{ $stripName }}</h3>
                    <p class="text-xs text-ink-faint">{{ $strip['note'] }}</p>
                </div>
                <ul class="divide-y divide-line">
                    @foreach (DesignTokens::colorGroup($strip['group']) as $name => $token)
                        <li class="flex items-stretch">
                            <span class="w-24 shrink-0 border-r border-line" style="background: var(--ui-{{ $name }})"></span>
                            <span class="flex min-w-0 flex-1 flex-col justify-center gap-0.5 px-4 py-2.5">
                                <span class="flex flex-wrap items-baseline justify-between gap-x-3">
                                    <span class="text-sm font-semibold text-ink">{{ $name }}</span>
                                    <span class="font-mono text-[11px] text-ink-faint">{{ $token['light'] }} · {{ $token['dark'] }}</span>
                                </span>
                                <span class="text-xs text-ink-faint">{{ $token['role'] }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</section>
