{{-- The customers list pane's cells (DSGN-006). An anonymous customer has
     no name, so the id steps into the supporting slot on line 2 — never
     the identity slot on line 1, which stays "Anonymous". --}}
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
        @endphp
        <x-admin.card-row
            href="{{ route('admin.customers.show', $customer) }}"
            :aria-current="$isSelected ? 'true' : null"
            data-pane-cell="{{ $customer->id }}"
            class="{{ $isSelected ? 'bg-stone-50 shadow-[inset_2px_0_0_0_var(--color-stone-500)] dark:bg-stone-800/60 dark:shadow-[inset_2px_0_0_0_var(--color-stone-500)]' : '' }}"
        >
            <div class="flex items-baseline gap-2">
                <span class="truncate font-medium {{ $customer->isAnonymous() ? 'text-stone-500 dark:text-stone-400' : '' }}">{{ $customer->isAnonymous() ? 'Anonymous' : $customer->displayName() }}</span>
                <span class="flex-1"></span>
                <x-admin.cell-time :at="$customer->created_at" />
            </div>
            <div class="flex items-center gap-2 text-stone-600 dark:text-stone-400">
                <x-admin.status-badge :tint="$tint">{{ $standingLabel }}</x-admin.status-badge>
                <span class="truncate {{ $customer->isAnonymous() ? 'font-mono' : '' }}">{{ $customer->isAnonymous() ? $customer->id : ($customer->email ?? '—') }}</span>
                <span class="flex-1"></span>
                <span class="font-mono tabular-nums text-stone-900 dark:text-stone-100">{{ $customer->orders_count }} order{{ $customer->orders_count === 1 ? '' : 's' }}</span>
            </div>
        </x-admin.card-row>
    @empty
        <x-admin.nothing class="m-3">No customers match.</x-admin.nothing>
    @endforelse
</div>
