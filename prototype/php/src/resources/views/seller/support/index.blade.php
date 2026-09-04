<x-layouts.seller title="Support — Art Store seller">
    <h1 class="text-xl font-semibold">Support</h1>
    <p class="mt-0.5 max-w-2xl text-gray-500 dark:text-gray-400">We are a two-person desk and we read every message. Ask about a payout, a listing, an order, or anything about selling here.</p>

    <div class="mt-5 grid grid-cols-1 items-stretch gap-5 lg:grid-cols-5">
        <section aria-labelledby="desk-heading" class="flex flex-col gap-5 rounded-lg border border-gray-200 bg-white p-6 lg:col-span-3 dark:border-white/10 dark:bg-gray-900">
            <h2 id="desk-heading" class="sr-only">The desk</h2>
            <div class="flex flex-wrap gap-8">
                @foreach ($desk->people as $person)
                    @php $initial = mb_strtoupper(mb_substr($person->name, 0, 1)); @endphp
                    <div class="flex items-center gap-3">
                        <span class="relative flex size-12 shrink-0 items-center justify-center rounded-full bg-gray-800 text-base font-semibold text-white dark:bg-gray-100 dark:text-gray-900">
                            {{ $initial }}
                            <span class="absolute right-0 bottom-0 size-3 rounded-full ring-2 ring-white dark:ring-gray-900 {{ $person->presence === \App\Domain\Seller\PresenceStatus::Online ? 'bg-green-500' : 'bg-gray-400' }}" aria-hidden="true"></span>
                        </span>
                        <div>
                            <p class="font-semibold text-gray-900 dark:text-white">{{ $person->name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $person->role }}</p>
                            <p class="text-xs {{ $person->presence === \App\Domain\Seller\PresenceStatus::Online ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400' }}">{{ $person->presenceText }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="text-gray-500 dark:text-gray-400">Typical reply in {{ $desk->replyTimePromise }}.</p>
            <div class="mt-auto flex flex-wrap items-center gap-3">
                <a href="{{ route('seller.support.create') }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">Start a conversation</a>
                @if ($desk->lastReplyTime !== null)
                    <span class="text-xs text-gray-500 dark:text-gray-400">Your last question was answered in {{ $desk->lastReplyTime->text }}.</span>
                @endif
            </div>
        </section>

        <section aria-labelledby="other-ways-heading" class="flex flex-col gap-4 rounded-lg border border-gray-200 bg-white p-6 lg:col-span-2 dark:border-white/10 dark:bg-gray-900">
            <h2 id="other-ways-heading" class="text-sm/6 font-semibold text-gray-900 dark:text-white">Other ways to reach us</h2>
            @php
                // A config value not yet set carries its bracketed
                // placeholder (config/support.php's own comment); an
                // operator can also leave the env var blank. Either way
                // it renders as plain text below, never as a link or a
                // number that reads as real.
                $isUnset = fn (?string $value): bool => $value === null || $value === '' || str_starts_with($value, '[');
                $email = config('support.email');
                $phone = config('support.phone');
                $bookingUrl = config('support.booking_url');
            @endphp
            <dl class="flex flex-col gap-4">
                <div>
                    <dt class="font-medium text-gray-900 dark:text-white">Email</dt>
                    <dd data-support-email class="text-gray-500 dark:text-gray-400">
                        @if ($isUnset($email))
                            Not published yet.
                        @else
                            {{ $email }}
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-900 dark:text-white">Phone</dt>
                    <dd data-support-phone class="text-gray-500 dark:text-gray-400">
                        @if ($isUnset($phone))
                            Not published yet.
                        @else
                            {{ $phone }} &middot; {{ config('support.phone_hours') }}
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-900 dark:text-white">A call</dt>
                    <dd class="text-gray-500 dark:text-gray-400">Book fifteen minutes for anything that is easier to talk through.</dd>
                    <dd class="mt-1" data-support-booking>
                        @if ($isUnset($bookingUrl))
                            <span class="text-gray-500 dark:text-gray-400">Not published yet.</span>
                        @else
                            <a href="{{ $bookingUrl }}" class="text-sm/6 font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">Pick a time</a>
                        @endif
                    </dd>
                </div>
            </dl>
        </section>
    </div>

    <div class="mt-8 grid grid-cols-1 items-start gap-8 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <h2 class="text-sm/6 font-semibold text-gray-900 dark:text-white">Help articles</h2>
            <div class="mt-2 grid grid-cols-1 gap-5 sm:grid-cols-2">
                @foreach ($helpGroups as $group => $articles)
                    <div class="rounded-lg border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-gray-900">
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $group }}</p>
                        <ul class="mt-2 flex flex-col gap-1.5">
                            @foreach ($articles as $article)
                                <li>
                                    <a href="{{ route('seller.support.articles.show', $article->slug) }}" class="text-sm/6 font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">{{ $article->title }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>

        <div>
            <h2 class="text-sm/6 font-semibold text-gray-900 dark:text-white">Your conversations with us</h2>
            <ul class="mt-2 overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
                @forelse ($threads as $thread)
                    <li class="border-t border-gray-100 first:border-t-0 dark:border-white/5">
                        <a href="{{ route('seller.messages.show', ['conversation' => $thread->conversationId, 'domain' => 'support']) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-white/5">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium text-gray-900 dark:text-white">{{ $thread->title }}</span>
                                <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ $thread->preview }}</span>
                            </span>
                            <x-seller.status-badge :tint="$thread->isResolved ? 'gray' : 'green'">{{ $thread->isResolved ? 'Resolved' : 'Open' }}</x-seller.status-badge>
                        </a>
                    </li>
                @empty
                    <li class="px-4 py-3 text-gray-500 dark:text-gray-400">No conversations yet.</li>
                @endforelse
            </ul>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-500">Every support thread also lives under Messages, in the Support tab.</p>
        </div>
    </div>
</x-layouts.seller>
