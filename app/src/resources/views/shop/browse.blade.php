<x-layouts.shop :title="$category->name.' — Art Store'">
    <h1 class="max-w-2xl font-display text-4xl leading-tight text-ink">{{ $category->name }}</h1>

    @if ($children->isNotEmpty())
        <nav aria-label="Browse by category" class="mt-6 flex flex-wrap gap-2">
            @foreach ($children as $child)
                <a href="{{ route('shop.browse', ['categoryPath' => $child->browsePath()]) }}"
                   class="inline-flex items-center gap-2 rounded-full border border-line-strong bg-surface px-4 py-2 text-sm font-semibold text-ink hover:border-accent">
                    {{ $child->name }}
                </a>
            @endforeach
        </nav>
    @endif

    @include('shop.partials.listing-grid', ['listings' => $listings])
</x-layouts.shop>
