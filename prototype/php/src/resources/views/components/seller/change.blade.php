{{-- How a figure reads against the range before it. `direction` is an
     `App\Domain\Analytics\ChangeDirection`; the color lives here, so the
     domain says which way a change went and the chrome says what that
     looks like. --}}
@props(['text', 'direction'])

@php
    $classes = match ($direction) {
        \App\Domain\Analytics\ChangeDirection::Up => 'text-green-600 dark:text-green-400',
        \App\Domain\Analytics\ChangeDirection::Down => 'text-red-600 dark:text-red-400',
        \App\Domain\Analytics\ChangeDirection::Flat => 'text-gray-500 dark:text-gray-400',
    };
@endphp

<span {{ $attributes->merge(['class' => 'text-sm font-medium tabular-nums '.$classes]) }}>{{ $text }}</span>
