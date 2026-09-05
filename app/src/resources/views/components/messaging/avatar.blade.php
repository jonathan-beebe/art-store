{{--
    A transcript message's avatar: a seller and a customer each keep the
    kind tag's own tint (indigo / pink), the desk sits in a solid dark
    circle regardless of which admin wrote — one avatar for a voice every
    admin shares. Admin-exclusive.
--}}
@props(['actor'])

@php
    $classes = match (true) {
        $actor instanceof \App\Models\Seller => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-400/10 dark:text-indigo-400',
        $actor instanceof \App\Models\Customer => 'bg-pink-50 text-pink-700 dark:bg-pink-400/10 dark:text-pink-400',
        $actor instanceof \App\Models\Admin => 'bg-stone-800 text-white dark:bg-stone-100 dark:text-stone-900',
        default => 'bg-stone-100 text-stone-500 dark:bg-white/10 dark:text-stone-400',
    };
@endphp

<span aria-hidden="true" {{ $attributes->merge(['class' => 'flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold '.$classes]) }}>{{ \App\View\ActorDisplay::initialsOf($actor) }}</span>
