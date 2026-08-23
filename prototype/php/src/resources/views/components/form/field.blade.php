@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'required' => false,
    'hint' => null,
])

@php
    $hintId = $hint !== null ? $name.'-hint' : null;
    $errorId = $name.'-error';
    $describedBy = trim($hintId.' '.($errors->has($name) ? $errorId : ''));
@endphp

<div {{ $attributes->only('class') }}>
    <label for="{{ $name }}" class="block font-medium text-gray-700">{{ $label }}</label>

    @if ($slot->isNotEmpty())
        {{ $slot }}
    @elseif ($type === 'textarea')
        <textarea id="{{ $name }}" name="{{ $name }}" @required($required) aria-describedby="{{ $describedBy }}"
                  {{ $attributes->except('class')->merge(['class' => 'mt-1 block w-full rounded border border-gray-400 px-3 py-2']) }}>{{ old($name, $value) }}</textarea>
    @else
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
               @unless ($type === 'file') value="{{ old($name, $value) }}" @endunless
               @required($required) aria-describedby="{{ $describedBy }}"
               {{ $attributes->except('class')->merge(['class' => 'mt-1 block w-full rounded border border-gray-400 px-3 py-2']) }}>
    @endif

    @if ($hint !== null)
        <p id="{{ $hintId }}" class="mt-1 text-gray-600">{{ $hint }}</p>
    @endif

    @error($name)
        <p id="{{ $errorId }}" class="mt-1 text-red-700">{{ $message }}</p>
    @enderror
</div>
