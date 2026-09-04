{{--
    What a thread is: a question about a piece, an order, or the support
    desk. Every list of threads in the seller portal wears the same pill,
    in the tint `App\Domain\Messaging\ConversationKind` names.
--}}
@props(['kind'])

@php
    $classes = match ($kind->tagTint()) {
        'indigo' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300',
        'green' => 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-400',
        default => 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-400',
    };
@endphp

<span class="inline-flex shrink-0 rounded px-1.5 py-0.5 text-[11px] font-medium tracking-wide uppercase {{ $classes }}">{{ $kind->tagLabel() }}</span>
