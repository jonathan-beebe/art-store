@php
    use App\Domain\Messaging\MessageBody;
    use Illuminate\Support\Str;
@endphp
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

            @php
                $sellerFirstName = $listing->seller->name ? Str::before($listing->seller->name, ' ') : $listing->seller->displayName();
            @endphp
            <section class="mt-14 border-t border-line pt-10">
                <h2 class="font-display text-xl text-ink">Ask {{ $sellerFirstName }} a question</h2>

                @if ($isSignedIn)
                    <p class="mt-3 max-w-lg text-ink-muted">Each question starts its own conversation with {{ $sellerFirstName }}. If it would help other people, they may publish the answer here.</p>

                    <form method="POST" action="{{ route('shop.listing.questions', $listing) }}" class="mt-5">
                        @csrf

                        <label for="body" class="sr-only">Your question</label>
                        <x-shop.messaging.composer
                            name="body"
                            :value="old('body', '')"
                            placeholder="Ask about size, materials, shipping…"
                            :maxlength="MessageBody::MAX_LENGTH"
                        />
                        @error('body')
                            <p class="mt-2 text-danger">{{ $message }}</p>
                        @enderror

                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            <x-ui.button variant="primary">Ask a question</x-ui.button>
                            <span class="text-sm text-ink-faint">You'll get an email when {{ $sellerFirstName }} replies.</span>
                        </div>
                    </form>
                @else
                    <p class="mt-3 max-w-lg text-ink-muted">{{ $sellerFirstName }} answers questions about size, materials, and shipping herself, and the answer lands in your messages. Sign in to ask — we'll bring you straight back here.</p>

                    <div class="mt-5 flex flex-wrap items-center gap-3">
                        <x-ui.button variant="primary" :href="route('auth.customer.login', ['redirect_to' => route('shop.listing', $listing)])">Sign in to ask</x-ui.button>
                        <span class="text-sm text-ink-faint">A magic link — no password.</span>
                    </div>
                @endif
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
