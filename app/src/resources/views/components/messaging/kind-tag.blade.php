{{--
    The inbox row's kind tag (docs/messaging.md's four `ConversationKind`
    cases, in the admin's own words): Seller / Customer for the two desk
    kinds, Order / Question for the two oversight kinds. Admin-exclusive —
    nothing else on the site lists all four kinds side by side.
--}}
@props(['kind'])

@php
    [$label, $classes] = match ($kind) {
        \App\Domain\Messaging\ConversationKind::AdminSeller => ['Seller', 'bg-indigo-50 text-indigo-700 dark:bg-indigo-400/10 dark:text-indigo-400'],
        \App\Domain\Messaging\ConversationKind::AdminCustomer => ['Customer', 'bg-pink-50 text-pink-700 dark:bg-pink-400/10 dark:text-pink-400'],
        \App\Domain\Messaging\ConversationKind::Fulfillment => ['Order', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400'],
        \App\Domain\Messaging\ConversationKind::ListingQuestion => ['Question', 'bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-400'],
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center rounded px-1.5 py-0.5 text-[11px] font-medium uppercase tracking-wide '.$classes]) }}>{{ $label }}</span>
