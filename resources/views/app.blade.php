<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=outfit:300,400,500,600,700" rel="stylesheet" />

        {{-- UNC Health's typeface. Only loaded when licensed files exist (see public/fonts/muller/README.md). --}}
        @php($mullerWeights = ['Light' => 300, 'Regular' => 400, 'Medium' => 500, 'Bold' => 700])
        <style>
            @foreach ($mullerWeights as $file => $weight)
                @if (file_exists(public_path("fonts/muller/Muller-{$file}.woff2")))
                    @font-face { font-family: 'Muller'; font-weight: {{ $weight }}; font-display: swap; src: url('/fonts/muller/Muller-{{ $file }}.woff2') format('woff2'); }
                @endif
            @endforeach
        </style>

        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
