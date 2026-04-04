<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Prime CRM' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('build/app.css') }}?v={{ filemtime(public_path('build/app.css')) }}">
    @livewireStyles
</head>
<body class="bg-crm-bg font-sans text-crm-t1 min-h-screen flex items-center justify-center">
    {{ $slot }}
    @livewireScripts
</body>
</html>
