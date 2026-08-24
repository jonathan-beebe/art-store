<x-layouts.shop title="Your account — Art Store">
    <h1 class="text-4xl font-semibold tracking-tight">Your account</h1>

    <dl class="mt-10 max-w-md">
        <dt class="text-sm font-medium uppercase tracking-wide text-neutral-500">Email</dt>
        <dd class="mt-1 text-lg">{{ $customer->email }}</dd>
    </dl>

    <div class="mt-8 flex flex-wrap gap-4">
        <form method="POST" action="{{ route('auth.customer.logout') }}">
            @csrf
            <button type="submit" class="rounded-full border border-neutral-300 px-6 py-2 text-base font-medium hover:border-neutral-900">
                Sign out
            </button>
        </form>

        <a href="{{ route('shop.support') }}" class="inline-flex items-center rounded-full border border-neutral-300 px-6 py-2 text-base font-medium hover:border-neutral-900">
            Contact support
        </a>
    </div>

    <h2 class="mt-16 text-2xl font-semibold tracking-tight">Notifications</h2>

    @if ($notifications->isEmpty())
        <p class="mt-6 text-lg text-neutral-600">Nothing yet. Order updates land here.</p>
    @else
        <ul class="mt-6 max-w-2xl divide-y divide-neutral-100 border-y border-neutral-100">
            @foreach ($notifications as $notification)
                <li class="flex flex-wrap items-start justify-between gap-6 py-5">
                    <div>
                        <p class="text-lg font-medium">{{ $notification->data['subject'] }}</p>
                        <p class="mt-1 text-base text-neutral-600">{{ $notification->data['body'] }}</p>
                    </div>

                    @if ($notification->read_at === null)
                        <form method="POST" action="{{ route('shop.account.notifications.read', $notification) }}">
                            @csrf
                            <button type="submit" class="text-sm text-neutral-500 underline hover:text-neutral-900">
                                Mark as read
                            </button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.shop>
