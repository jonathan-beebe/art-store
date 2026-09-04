{{--
    The shell every status-code error page in resources/views/errors/ shares.
    An error can land on any of the three sites (a 404 for a mistyped URL, a
    419 on any site's form), and there is no reliable way to tell which one
    from the exception alone — Laravel resolves errors/{status}.blade.php by
    status code only, with no route or guard context handed in. Rather than
    guess a site and risk a nav bar for a guard the visitor never signed into,
    every status code gets this one neutral page instead of the three
    per-site variants the rate-limited pages use.
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
<body class="supports-dark flex h-full flex-col items-center justify-center bg-canvas font-sans text-sm text-ink antialiased">
    <main class="mx-auto max-w-md px-4 text-center">
        {{ $slot }}
    </main>
</body>
</html>
