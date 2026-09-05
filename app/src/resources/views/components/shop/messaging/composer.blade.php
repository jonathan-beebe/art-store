{{--
    The storefront's one composer box: a textarea that grows with its
    content (`field-sizing-content`, CSS only), a live counter, and a
    Cmd/Ctrl+Enter hint — `public/composer.js` wires the counter and the
    keyboard shortcut onto `data-composer`; the form still posts without it,
    since the counter renders server-side first and Enter alone stays a
    newline. `$slot` holds whatever sits at the strip's right edge — a Send
    button for a reply composer, nothing for a page that puts its primary
    button below the box instead. Labelling the textarea is the caller's:
    a page with its own visible label passes none of its own here, and a
    page with none (the thread's reply box) supplies a `sr-only` one — one
    `<label for="…">` per field, never two.
--}}
@props(['name' => 'body', 'value' => '', 'placeholder' => null, 'maxlength'])

<div {{ $attributes->class(['overflow-hidden rounded-card border border-line-strong bg-surface']) }}>
    <textarea
        id="{{ $name }}" name="{{ $name }}" data-composer required maxlength="{{ $maxlength }}"
        placeholder="{{ $placeholder }}"
        class="field-sizing-content block max-h-72 min-h-[7.5rem] w-full resize-none border-0 bg-transparent px-4 py-3 text-lg leading-relaxed text-ink placeholder:text-ink-faint focus:outline-none"
    >{{ $value }}</textarea>

    <div class="flex items-center gap-3 border-t border-line px-4 py-2.5">
        <span data-composer-count class="text-sm text-ink-faint">{{ number_format(mb_strlen((string) $value)) }} / {{ number_format($maxlength) }}</span>
        <span class="ml-auto hidden items-center gap-1 text-sm text-ink-faint sm:flex">
            <kbd data-composer-mod class="rounded border border-line-strong bg-canvas px-1.5 py-0.5 font-mono text-[11px]">Ctrl</kbd>
            <kbd class="rounded border border-line-strong bg-canvas px-1.5 py-0.5 font-mono text-[11px]">Enter</kbd>
            to send
        </span>
        {{ $slot }}
    </div>
</div>
