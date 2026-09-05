@props(['subject'])

{{--
    The home page's featured band (DSGN-007): one configured listing or
    category, full viewport width. The page renders this into the shop
    layout's `beforeMain` slot — outside `<main>`'s centered `max-w-6xl`
    column, the same way `<header>` already sits full width with its own
    content centered inside it — so the photograph reaches both edges
    without a CSS bleed trick. `$subject` is a resolved
    {@see \App\Shop\FeaturedSubject}; the caller renders this
    component only when one exists.
--}}
<section aria-labelledby="featured-heading" class="border-b border-line bg-surface">
    <div class="grid sm:grid-cols-3">
        <div class="aspect-[4/3] w-full bg-cover bg-center sm:aspect-auto sm:col-span-2 sm:min-h-[28rem]"
             style="background-image: url('{{ $subject->imageUrl }}')" role="img" aria-label="{{ $subject->title }}"></div>

        <div class="flex flex-col justify-center gap-5 px-8 py-12 sm:col-span-1 sm:px-12 sm:py-16">
            <p class="text-xs font-bold uppercase tracking-wide text-accent">Featured</p>
            <h2 id="featured-heading" class="font-display text-3xl leading-tight text-ink sm:text-4xl">{{ $subject->title }}</h2>

            @if ($subject->description !== '')
                <p class="text-ink-muted">{{ $subject->description }}</p>
            @endif

            @if ($subject->price !== null || $subject->byline !== null)
                <div class="flex flex-wrap items-baseline gap-3">
                    @if ($subject->price !== null)
                        <span class="font-display text-2xl text-ink">{{ $subject->price }}</span>
                    @endif
                    @if ($subject->byline !== null)
                        <span class="text-sm text-ink-faint">{{ $subject->byline }}</span>
                    @endif
                </div>
            @endif

            <div>
                <x-ui.button variant="primary" :href="$subject->ctaHref">{{ $subject->ctaLabel }}</x-ui.button>
            </div>
        </div>
    </div>
</section>
