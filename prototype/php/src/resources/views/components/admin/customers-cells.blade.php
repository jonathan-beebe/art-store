{{-- The customers list pane's cells, in the shared pane-row shape. An
     anonymous customer has no name, so its title stays "Anonymous" and its
     id steps into the supporting line instead — never the title. The meta
     column holds the standing badge over the order count — the created
     date dropped as a low-value fact, and the order count moved out of the
     supporting line, which otherwise truncates the email/id it shares that
     line with at pane width. --}}
@props(['customers', 'selected' => null])

<div class="flex flex-col divide-y divide-stone-200 dark:divide-stone-800">
    @forelse ($customers as $customer)
        @php
            $isSelected = $selected !== null && $selected->id === $customer->id;
            $tint = match (true) {
                (bool) $customer->activeBlock => 'bad',
                $customer->isAnonymous() => 'neutral',
                $customer->isVerified() => 'ok',
                default => 'warn',
            };
            $standingLabel = match (true) {
                (bool) $customer->activeBlock => 'Blocked',
                $customer->isAnonymous() => 'Anonymous',
                $customer->isVerified() => 'Verified',
                default => 'Unverified',
            };
            $name = $customer->isAnonymous() ? 'Anonymous' : $customer->displayName();
            $identifier = $customer->isAnonymous() ? $customer->id : ($customer->email ?? '—');
            $initials = $customer->isAnonymous() ? '?' : collect(preg_split('/\s+/', trim($name)))
                ->filter()
                ->take(2)
                ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
                ->implode('');
        @endphp
        <x-pane-row
            accent="stone"
            :selected="$isSelected"
            href="{{ route('admin.customers.show', $customer) }}"
            :aria-current="$isSelected ? 'true' : null"
            data-pane-cell="{{ $customer->id }}"
        >
            <x-slot:leading>
                <span class="flex size-12 flex-none items-center justify-center rounded-full bg-stone-100 text-sm font-medium text-stone-600 dark:bg-stone-800 dark:text-stone-300">{{ $initials }}</span>
            </x-slot:leading>
            <x-slot:title>
                <p class="truncate text-sm/6 font-semibold {{ $customer->isAnonymous() ? 'text-stone-500 dark:text-stone-400' : 'text-stone-900 dark:text-white' }}">{{ $name }}</p>
            </x-slot:title>
            <x-slot:supporting>
                <p class="mt-1 truncate text-xs/5 {{ $customer->isAnonymous() ? 'font-mono' : '' }} text-stone-500 dark:text-stone-400">{{ $identifier }}</p>
            </x-slot:supporting>
            <x-slot:meta>
                <x-admin.status-badge :tint="$tint">{{ $standingLabel }}</x-admin.status-badge>
                <p class="mt-1 text-xs/5 text-stone-500 dark:text-stone-400">{{ $customer->orders_count }} order{{ $customer->orders_count === 1 ? '' : 's' }}</p>
            </x-slot:meta>
        </x-pane-row>
    @empty
        <x-admin.nothing class="m-3">No customers match.</x-admin.nothing>
    @endforelse
</div>
