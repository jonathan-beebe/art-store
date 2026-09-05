{{--
    The bare shell a design-system specimen renders in: theme tokens, fonts,
    and a canvas body — no header, no nav. Specimens load inside the
    design-system page's phone-frame iframes, where media queries and
    fixed positioning resolve to the frame's own viewport.
--}}
@props(['title'])

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css'])
    <x-theme-css />
</head>
<body class="supports-dark h-full bg-canvas font-body text-ink antialiased">
    {{ $slot }}
</body>
</html>
