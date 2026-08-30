{{-- One id, shown as a small pill: linked when `href` is given, plain
     otherwise. `:truncate="true"` (the default) shows the prefix plus 8
     body characters — a collapsed row's chips — while the link's href,
     `title`, and accessible name always carry the full id
     (`App\Logging\Admin\LogIdLinks::truncate()`); pass `:truncate="false"`
     for an expanded panel or the story view, which show ids in full. --}}
@props(['id', 'href' => null, 'truncate' => true])

@php
    $text = $truncate ? \App\Logging\Admin\LogIdLinks::truncate($id) : $id;
    $classes = 'inline-flex items-center rounded border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 font-mono text-xs text-gray-700 dark:text-gray-300';
@endphp

@if ($href !== null)
    <a href="{{ $href }}" title="{{ $id }}" aria-label="{{ $id }}" {{ $attributes->merge(['class' => $classes.' underline decoration-dotted decoration-gray-400 dark:decoration-gray-500']) }}>{{ $text }}</a>
@else
    <span title="{{ $id }}" aria-label="{{ $id }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $text }}</span>
@endif
