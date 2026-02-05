<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>{{ config('app.name', 'HubTube') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    
    @php
        $siteTitleFont = \App\Models\Setting::get('site_title_font', '');
    @endphp
    @if($siteTitleFont)
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family={{ str_replace(' ', '+', $siteTitleFont) }}&display=swap" rel="stylesheet">
    @endif

    <style>
        :root {
            --color-bg-primary: #0a0a0a;
            --color-bg-secondary: #171717;
            --color-bg-card: #1f1f1f;
            --color-accent: #ef4444;
            --color-text-primary: #ffffff;
            --color-text-secondary: #a3a3a3;
            --color-border: #262626;
        }
    </style>

    @routes
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="font-sans antialiased" style="background-color: var(--color-bg-primary); color: var(--color-text-primary);">
    @inertia
</body>
</html>
