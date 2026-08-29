<x-layouts.shop :title="$listing->title.' — Art Store'">
    <article class="grid gap-12 lg:grid-cols-2">
        <div>
            @include('shop.partials.listing-images', ['listing' => $listing])
        </div>

        <div class="max-w-lg">
            <h1 class="font-display text-4xl leading-tight text-ink">{{ $listing->title }}</h1>
            <p class="mt-6 text-2xl text-ink">{{ $listing->price()->format() }}</p>

            <div class="mt-6 flex items-center gap-3 rounded-field border border-line bg-surface px-4 py-3">
                <x-ui.avatar :name="$listing->seller->displayName()" size="md" />
                <p class="text-sm font-semibold text-ink">Made by {{ $listing->seller->displayName() }}</p>
            </div>

            <dl class="mt-8 grid grid-cols-2 gap-y-4 border-y border-line py-6 text-base">
                @if ($listing->mediumAttributeLabel() !== null)
                    <dt class="text-ink-faint">Medium</dt>
                    <dd class="text-ink">{{ $listing->mediumAttributeLabel() }}</dd>
                @endif
                <dt class="text-ink-faint">Dimensions</dt>
                <dd class="text-ink">{{ $listing->dimensions ?? 'Unlisted' }}</dd>
                <dt class="text-ink-faint">Available</dt>
                <dd class="text-ink">{{ $isPurchasable ? $listing->quantityLabel() : 'Sold' }}</dd>
            </dl>

            @if (! empty($highlights))
                <section aria-labelledby="highlights-heading" class="mt-8 border-b border-line pb-6">
                    <h2 id="highlights-heading" class="text-sm font-semibold uppercase tracking-wide text-ink-faint">Highlights</h2>
                    <dl class="mt-4 grid grid-cols-2 gap-y-4 text-base">
                        @foreach ($highlights as $highlight)
                            <dt class="text-ink-faint">{{ $highlight['name'] }}</dt>
                            <dd class="text-ink">{{ implode(', ', $highlight['values']) }}</dd>
                        @endforeach
                    </dl>
                </section>
            @endif

            @include('shop.partials.listing-description', ['listing' => $listing])

            <div class="mt-10">
                @if ($hasConfigurator)
                    @include('shop.partials.configurator', ['listing' => $listing, 'configuration' => $configuration, 'focusId' => $focusId, 'mode' => 'shop', 'refreshUrl' => route('shop.listing', $listing)])
                @elseif ($isPurchasable)
                    @include('shop.partials.add-to-cart-button', ['mode' => 'shop', 'listing' => $listing])
                @endif

                <form method="POST" action="{{ route('shop.favorites.toggle', $listing) }}" class="mt-4">
                    @csrf
                    <x-ui.button variant="secondary">
                        {{ $isFavorited ? 'Remove from favorites' : 'Favorite' }}
                    </x-ui.button>
                </form>
            </div>

            <section class="mt-14 border-t border-line pt-10">
                <h2 class="font-display text-xl text-ink">Ask {{ $listing->seller->displayName() }} a question</h2>

                <form method="POST" action="{{ route('shop.listing.questions', $listing) }}" class="mt-4">
                    @csrf

                    <label for="body" class="sr-only">Your question</label>
                    <x-ui.textarea id="body" name="body" required rows="3" maxlength="2000"
                                   placeholder="Ask about size, materials, shipping…">{{ old('body') }}</x-ui.textarea>
                    @error('body')
                        <p class="mt-2 text-danger">{{ $message }}</p>
                    @enderror

                    <x-ui.button variant="primary" class="mt-4">
                        Ask a question
                    </x-ui.button>
                </form>
            </section>

            @if ($listing->faqs->isNotEmpty())
                <section class="mt-14 border-t border-line pt-10">
                    <h2 class="font-display text-xl text-ink">Questions &amp; answers</h2>

                    <dl class="mt-6 space-y-6">
                        @foreach ($listing->faqs as $faq)
                            <div>
                                <dt class="font-semibold text-ink">{{ $faq->question }}</dt>
                                <dd class="mt-1 text-ink-muted">{{ $faq->answer }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endif
        </div>
    </article>
</x-layouts.shop>
