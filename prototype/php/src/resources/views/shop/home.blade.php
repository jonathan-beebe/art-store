<x-layouts.shop title="Art Store">
    <div class="flex flex-wrap items-end justify-between gap-8">
        <h1 class="max-w-2xl text-4xl font-semibold leading-tight tracking-tight">
            @if ($search->hasTerm())
                Art matching “{{ $search->term }}”
            @else
                Hand-made art, straight from the artist
            @endif
        </h1>

        <form method="GET" action="{{ route('shop.home') }}" class="flex items-end gap-3">
            @if ($search->hasTerm())
                <input type="hidden" name="q" value="{{ $search->term }}">
            @endif

            <div>
                <label for="medium" class="block text-xs font-medium uppercase tracking-wide text-neutral-500">Medium</label>
                <select id="medium" name="medium"
                        class="mt-2 rounded-lg border border-neutral-200 px-4 py-2 text-base">
                    <option value="">All media</option>
                    @foreach ($media as $option)
                        <option value="{{ $option }}" @selected($search->medium === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="rounded-lg border border-neutral-300 px-5 py-2 text-base font-medium hover:border-neutral-900">
                Filter
            </button>
        </form>
    </div>

    @if ($listings->isEmpty())
        <p class="mt-16 text-lg text-neutral-600">No art matches that yet.</p>
    @else
        <ul class="mt-14 grid grid-cols-1 gap-x-8 gap-y-14 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($listings as $listing)
                <li><x-listing-card :listing="$listing" /></li>
            @endforeach
        </ul>

        <div class="mt-16">{{ $listings->links() }}</div>
    @endif
</x-layouts.shop>
