{{--
    A store as a buyer meets it, in the Warm Craft tokens. The seller's
    Store screen renders it compact beside the form; `/s/{slug}` renders it
    full, with the maker's listings below.

    `compact` shortens the story to its opening and shows the first three
    gallery pictures, so the preview column stays one screen tall.
--}}
@props(['profile', 'facts', 'compact' => false])

@php
    use App\Domain\Store\StoreSectionKind;
    use Illuminate\Support\Str;

    $nameTag = $compact ? 'h3' : 'h1';
    // One level under the name, so a section heading never outranks it:
    // full mode's h1 name takes h2 sections, compact's h3 name takes h4.
    $sectionHeadingTag = $compact ? 'h4' : 'h2';
    $storyLimit = 240;
    $galleryLimit = $compact ? 3 : 8;
@endphp

<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-card bg-surface text-ink ring-1 ring-line']) }}>
    @if ($profile->coverImage)
        <img src="{{ $profile->coverImage->url() }}" alt="{{ $profile->coverImage->alt ?? '' }}"
             class="block aspect-[3/1] w-full object-cover">
    @else
        <div class="aspect-[3/1] w-full bg-accent-soft"></div>
    @endif

    <div class="px-5 pb-5 @unless ($compact) sm:px-8 sm:pb-8 @endunless">
        @if ($profile->portraitImage)
            <img src="{{ $profile->portraitImage->url() }}" alt="{{ $profile->portraitImage->alt ?? '' }}"
                 class="-mt-9 block size-18 rounded-full object-cover ring-4 ring-surface">
        @else
            <x-ui.avatar :name="$profile->name" size="lg" class="-mt-9 ring-4 ring-surface" />
        @endif

        <{{ $nameTag }} class="mt-3 font-display {{ $compact ? 'text-xl' : 'text-3xl' }} leading-snug text-ink">{{ $profile->name }}</{{ $nameTag }}>

        @if ($profile->tagline)
            <p class="mt-1 text-ink-muted">{{ $profile->tagline }}</p>
        @endif

        <p class="mt-2 text-sm text-ink-faint">
            @if ($profile->location){{ $profile->location }} · @endif{{ $facts->sentence() }}
        </p>

        @foreach ($profile->sections as $section)
            @if ($section->kind === StoreSectionKind::Story && $section->body)
                <section class="mt-4">
                    @if ($section->heading)
                        <{{ $sectionHeadingTag }} class="font-display {{ $compact ? 'text-base' : 'text-xl' }} text-ink">{{ $section->heading }}</{{ $sectionHeadingTag }}>
                    @endif

                    @if ($compact)
                        <p class="mt-1 text-sm/6 text-ink">{{ Str::limit($section->body, $storyLimit) }}</p>
                        @if (mb_strlen($section->body) > $storyLimit)
                            <p class="mt-1.5 text-sm text-accent">Read the whole story</p>
                        @endif
                    @else
                        @foreach (preg_split('/\R{2,}/', trim($section->body)) ?: [] as $paragraph)
                            <p class="mt-3 text-base/7 text-ink">{{ $paragraph }}</p>
                        @endforeach
                    @endif
                </section>
            @elseif ($section->kind === StoreSectionKind::Gallery && $section->sectionImages->isNotEmpty())
                <section class="mt-4">
                    @if ($section->heading)
                        <{{ $sectionHeadingTag }} class="font-display {{ $compact ? 'text-base' : 'text-xl' }} text-ink">{{ $section->heading }}</{{ $sectionHeadingTag }}>
                    @endif

                    <div class="mt-2 grid gap-2 {{ $compact ? 'grid-cols-3' : 'grid-cols-2 sm:grid-cols-4' }}">
                        @foreach ($section->sectionImages->take($galleryLimit) as $placement)
                            @if ($placement->storeImage)
                                <img src="{{ $placement->storeImage->url() }}" alt="{{ $placement->storeImage->alt ?? '' }}"
                                     loading="lazy" class="aspect-square w-full rounded-card object-cover">
                            @endif
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach

        @if ($profile->links->isNotEmpty())
            <p class="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                @foreach ($profile->links as $link)
                    <a href="{{ $link->href() }}" rel="noopener nofollow" class="text-accent hover:text-accent-strong">{{ $link->display() }}</a>
                @endforeach
            </p>
        @endif

        {{ $slot }}
    </div>
</div>
