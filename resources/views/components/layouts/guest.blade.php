<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Prime CRM' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'sans-serif'], mono: ['JetBrains Mono', 'monospace'] },
                    colors: {
                        crm: { bg: '#ffffff', surface: '#f7f8fa', card: '#eef0f4', hover: '#e2e5ea', border: '#d1d5db', 'border-h': '#b0b5be', t1: '#111111', t2: '#4b5563', t3: '#9ca3af' }
                    }
                }
            }
        }
    </script>
    @livewireStyles
</head>
<body class="bg-crm-bg font-sans text-crm-t1 min-h-screen flex items-center justify-center">
    {{ $slot }}
    @livewireScripts
</body>
</html>
