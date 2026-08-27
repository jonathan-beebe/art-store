<x-layouts.shop :title="$listing->title.' — Art Store'">
    <article class="grid gap-12 lg:grid-cols-2">
        <div>
            <img src="{{ $listing->imageUrl() }}" alt="{{ $listing->title }}"
                 class="aspect-square w-full rounded-3xl object-cover">

            @if ($listing->images->count() > 1)
                <div class="mt-4 grid grid-cols-4 gap-3">
                    @foreach ($listing->images->skip(1)->values() as $image)
                        <img src="{{ $image->url() }}" alt="{{ $listing->title }} — photo {{ $loop->iteration + 1 }}"
                             class="aspect-square w-full rounded-xl object-cover">
                    @endforeach
                </div>
            @endif
        </div>

        <div class="max-w-lg">
            <h1 class="text-4xl font-semibold leading-tight tracking-tight">{{ $listing->title }}</h1>
            <p class="mt-3 text-lg text-neutral-600">{{ $listing->seller->displayName() }}</p>
            <p class="mt-8 text-2xl">{{ $listing->price()->format() }}</p>

            <dl class="mt-8 grid grid-cols-2 gap-y-4 border-y border-neutral-100 py-6 text-base">
                @if ($listing->mediumAttributeLabel() !== null)
                    <dt class="text-neutral-500">Medium</dt>
                    <dd>{{ $listing->mediumAttributeLabel() }}</dd>
                @endif
                <dt class="text-neutral-500">Dimensions</dt>
                <dd>{{ $listing->dimensions ?? 'Unlisted' }}</dd>
                <dt class="text-neutral-500">Available</dt>
                <dd>{{ $isPurchasable ? $listing->quantity : 'Sold' }}</dd>
            </dl>

            @if (! empty($highlights))
                <section aria-labelledby="highlights-heading" class="mt-8 border-b border-neutral-100 pb-6">
                    <h2 id="highlights-heading" class="text-sm font-semibold uppercase tracking-wide text-neutral-500">Highlights</h2>
                    <dl class="mt-4 grid grid-cols-2 gap-y-4 text-base">
                        @foreach ($highlights as $highlight)
                            <dt class="text-neutral-500">{{ $highlight['name'] }}</dt>
                            <dd>{{ implode(', ', $highlight['values']) }}</dd>
                        @endforeach
                    </dl>
                </section>
            @endif

            <p class="mt-8 text-lg leading-relaxed text-neutral-700">{{ $listing->description }}</p>

            @if ($listing->descriptionSections->isNotEmpty())
                @include('shop.partials.description-sections', [
                    'sections' => $listing->descriptionSections,
                    'sectionClass' => 'mt-14 border-t border-neutral-100 pt-10',
                    'headingTag' => 'h2',
                    'headingClass' => 'text-xl font-semibold tracking-tight',
                ])
            @endif

            <div class="mt-10">
                @if ($hasConfigurator)
                    @include('shop.partials.configurator', ['listing' => $listing, 'configuration' => $configuration])
                @elseif ($isPurchasable)
                    <form method="POST" action="{{ route('shop.cart.add', $listing) }}">
                        @csrf
                        <button type="submit" class="rounded-full bg-neutral-900 px-8 py-3 text-base font-medium text-white">
                            Add to cart
                        </button>
                    </form>
                @endif

                <form method="POST" action="{{ route('shop.favorites.toggle', $listing) }}" class="mt-4">
                    @csrf
                    <button type="submit" class="rounded-full border border-neutral-300 px-8 py-3 text-base font-medium hover:border-neutral-900">
                        {{ $isFavorited ? 'Remove from favorites' : 'Favorite' }}
                    </button>
                </form>
            </div>

            <section class="mt-14 border-t border-neutral-100 pt-10">
                <h2 class="text-xl font-semibold tracking-tight">Ask the seller a question</h2>

                <form method="POST" action="{{ route('shop.listing.questions', $listing) }}" class="mt-4">
                    @csrf

                    <label for="body" class="sr-only">Your question</label>
                    <textarea id="body" name="body" required rows="3" maxlength="2000"
                              placeholder="Ask about size, materials, shipping…"
                              class="block w-full rounded-2xl border border-neutral-300 px-4 py-3 text-base placeholder:text-neutral-400 focus:border-neutral-900 focus:outline-none">{{ old('body') }}</textarea>
                    @error('body')
                        <p class="mt-2 text-red-700">{{ $message }}</p>
                    @enderror

                    <button type="submit" class="mt-4 rounded-full bg-neutral-900 px-8 py-3 text-base font-medium text-white">
                        Ask a question
                    </button>
                </form>
            </section>

            @if ($listing->faqs->isNotEmpty())
                <section class="mt-14 border-t border-neutral-100 pt-10">
                    <h2 class="text-xl font-semibold tracking-tight">Questions &amp; answers</h2>

                    <dl class="mt-6 space-y-6">
                        @foreach ($listing->faqs as $faq)
                            <div>
                                <dt class="font-medium">{{ $faq->question }}</dt>
                                <dd class="mt-1 text-neutral-700">{{ $faq->answer }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endif
        </div>
    </article>
</x-layouts.shop>
