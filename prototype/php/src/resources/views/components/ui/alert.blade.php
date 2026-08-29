@props(['tone' => 'danger'])

@php
    $classes = match ($tone) {
        'danger' => 'border-danger-line bg-danger-surface text-danger',
        'success' => 'border-success-line bg-success-surface text-success',
        'notice' => 'border-notice-line bg-notice-surface text-notice',
    };
@endphp

<div {{ $attributes->merge(['role' => $tone === 'danger' ? 'alert' : 'status', 'class' => 'rounded-card border px-6 py-4 '.$classes]) }}>{{ $slot }}</div>
