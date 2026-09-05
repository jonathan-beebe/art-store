{{-- One id, shown as a small pill: linked when `href` is given, plain
     otherwise. `:truncate="true"` (the default) shows the prefix plus 8
     body characters — a collapsed row's chips — while the link's href,
     `title`, and accessible name always carry the full id
     (`App\Logging\Admin\LogIdLinks::truncate()`); pass `:truncate="false"`
     for an expanded panel or the story view, which show ids in full. --}}
@props(['id', 'href' => null, 'truncate' => true])

@php
    $text = $truncate ? \App\Logging\Admin\LogIdLinks::truncate($id) : $id;
    $classes = 'inline-flex items-center rounded-md bg-stone-100 dark:bg-stone-400/10 px-2 py-0.5 font-mono text-xs text-stone-700 dark:text-stone-300 inset-ring inset-ring-stone-500/10 dark:inset-ring-stone-400/20';
@endphp

@if ($href !== null)
    <a href="{{ $href }}" title="{{ $id }}" aria-label="{{ $id }}" {{ $attributes->merge(['class' => $classes.' underline decoration-dotted decoration-stone-400 dark:decoration-stone-500']) }}>{{ $text }}</a>
@else
    <span title="{{ $id }}" aria-label="{{ $id }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $text }}</span>
@endif
