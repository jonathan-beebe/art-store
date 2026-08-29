<x-layouts.shop title="Your account — Art Store">
    <h1 class="font-display text-4xl leading-tight text-ink">Your account</h1>

    <dl class="mt-10 max-w-md">
        <dt class="text-sm font-medium uppercase tracking-wide text-ink-faint">Email</dt>
        <dd class="mt-1 text-lg text-ink">{{ $customer->email }}</dd>
    </dl>

    <div class="mt-8 flex flex-wrap gap-4">
        <form method="POST" action="{{ route('auth.customer.logout') }}">
            @csrf
            <x-ui.button variant="secondary">
                Sign out
            </x-ui.button>
        </form>

        <a href="{{ route('shop.support') }}" class="inline-flex items-center rounded-full border border-line-strong bg-surface px-6 py-2 text-base font-medium text-ink hover:border-accent hover:text-accent">
            Contact support
        </a>
    </div>

    <h2 class="mt-16 font-display text-2xl text-ink">Notifications</h2>

    @if ($notifications->isEmpty())
        <p class="mt-6 text-lg text-ink-muted">Nothing yet. Order updates land here.</p>
    @else
        <ul class="mt-6 max-w-2xl divide-y divide-line border-y border-line">
            @foreach ($notifications as $notification)
                <li class="flex flex-wrap items-start justify-between gap-6 py-5">
                    <div>
                        <p class="text-lg font-medium text-ink">{{ $notification->data['subject'] }}</p>
                        <p class="mt-1 text-base text-ink-muted">{{ $notification->data['body'] }}</p>
                    </div>

                    @if ($notification->read_at === null)
                        <form method="POST" action="{{ route('shop.account.notifications.read', $notification) }}">
                            @csrf
                            <button type="submit" class="text-sm text-ink-faint underline hover:text-accent">
                                Mark as read
                            </button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.shop>
