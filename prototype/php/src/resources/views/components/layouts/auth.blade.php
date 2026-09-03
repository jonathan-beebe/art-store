@props(['title', 'accent' => 'indigo'])

@php
    $mark = $accent === 'stone' ? 'bg-stone-600' : 'bg-indigo-600';
    $heading = $accent === 'stone' ? 'text-stone-900 dark:text-white' : 'text-gray-900 dark:text-white';
@endphp

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css'])
    <x-theme-css />
</head>
<body class="supports-dark flex h-full flex-col bg-gray-100 font-sans text-sm text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-gray-900 focus:px-4 focus:py-2 focus:font-medium focus:text-white dark:focus:bg-gray-100 dark:focus:text-gray-900">Skip to content</a>

    <x-debug-alert />

    <main id="main-content" class="flex flex-1 flex-col justify-center px-6 py-12 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-sm">
            <span aria-hidden="true" class="mx-auto flex size-10 items-center justify-center rounded-lg {{ $mark }} text-sm font-semibold text-white">A</span>
            <h1 class="mt-10 text-center text-2xl/9 font-bold tracking-tight {{ $heading }}">Sign in</h1>
        </div>

        <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-sm">
            @if ($errors->get('rate_limit'))
                <div role="alert" class="mb-6 rounded-md border border-red-300 dark:border-red-900 bg-red-50 dark:bg-red-950/40 p-4 text-red-900 dark:text-red-200">
                    @foreach ($errors->get('rate_limit') as $message)
                        <p>{{ $message }}</p>
                    @endforeach
                </div>
            @endif

            {{ $slot }}
        </div>
    </main>
</body>
</html>
