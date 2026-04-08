<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- PWA / App Shell --}}
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#2563eb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="CRM Prime">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="CRM Prime">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/icon-192.png') }}">

    <title>{{ $title ?? 'CRM Prime' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('build/app.css') }}?v={{ filemtime(public_path('build/app.css')) }}">
    <style>
        [x-cloak]{display:none!important}
        /* PWA standalone safe areas */
        @supports(padding: env(safe-area-inset-top)) {
            .pwa-safe-top { padding-top: env(safe-area-inset-top); }
            .pwa-safe-bottom { padding-bottom: env(safe-area-inset-bottom); }
        }
    </style>
    @livewireStyles
</head>
<body class="bg-crm-bg font-sans text-crm-t1 min-h-screen" x-data="{ drawerOpen: false }">

    {{-- Top Bar --}}
    <header class="fixed top-0 left-0 right-0 h-12 bg-crm-surface border-b border-crm-border flex items-center px-4 z-40 pwa-safe-top">
        <button @click="drawerOpen = !drawerOpen" class="w-10 h-10 flex items-center justify-center rounded-lg hover:bg-crm-hover transition">
            <svg class="w-5 h-5 text-crm-t2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <h2 class="ml-3 text-base font-bold capitalize">{{ $title ?? 'Dashboard' }}</h2>
        <div class="ml-auto flex items-center gap-3">
            <span class="text-xs px-2.5 py-1 rounded-full font-semibold uppercase tracking-wide"
                  style="background: {{ auth()->user()->color ?? '#3b82f6' }}22; color: {{ auth()->user()->color ?? '#3b82f6' }}">
                {{ str_replace('_', ' ', auth()->user()->role) }}
            </span>
            <span class="text-xs text-crm-t3">{{ auth()->user()->name }}</span>
            @if(auth()->user()->avatar_path)
                <img src="{{ asset('storage/' . auth()->user()->avatar_path) }}" class="w-8 h-8 rounded-full object-cover">
            @else
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-[11px] font-semibold text-white"
                     style="background: {{ auth()->user()->color ?? '#3b82f6' }}">
                    {{ auth()->user()->avatar ?? substr(auth()->user()->name, 0, 2) }}
                </div>
            @endif
        </div>
    </header>

    {{-- Drawer Overlay --}}
    <div x-show="drawerOpen" x-transition.opacity @click="drawerOpen = false"
         class="fixed inset-0 bg-black/50 z-40 backdrop-blur-sm" style="display: none;"></div>

    {{-- Hamburger Drawer --}}
    <nav x-show="drawerOpen" x-transition:enter="transform transition ease-out duration-200"
         x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
         x-transition:leave="transform transition ease-in duration-150"
         x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
         class="fixed top-0 left-0 h-full w-72 bg-crm-surface border-r border-crm-border z-50 flex flex-col overflow-y-auto"
         style="display: none;">

        {{-- Drawer Header --}}
        <div class="flex items-center justify-between px-4 h-12 border-b border-crm-border flex-shrink-0">
            <div class="flex items-center gap-2">
                <img src="{{ asset('images/prime-logo.png') }}" alt="Prime" class="w-8 h-8 rounded-lg object-contain" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <div class="w-8 h-8 bg-white border border-crm-border rounded-lg items-center justify-center" style="display:none">
                    <svg viewBox="0 0 100 100" class="w-5 h-5"><path d="M20 90V10h35c20 0 32 12 32 28s-12 28-32 28H38v24H20zm18-40h15c10 0 16-6 16-14s-6-14-16-14H38v28z" fill="#111" stroke="#111" stroke-width="3"/></svg>
                </div>
                <span class="font-extrabold text-sm tracking-widest">PRIME CRM</span>
            </div>
            <button @click="drawerOpen = false" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-crm-hover text-crm-t3">&times;</button>
        </div>

        {{-- Nav Items --}}
        <div class="flex-1 py-2 px-2 space-y-0.5">
            @php
                $user = auth()->user();
                $moduleEnabled = function (string $key, bool $default = true): bool {
                    try {
                        $raw = \Illuminate\Support\Facades\DB::table('crm_settings')->where('key', $key)->value('value');
                        if ($raw === null) return $default;
                        $decoded = json_decode($raw, true);
                        return is_bool($decoded) ? $decoded : $default;
                    } catch (\Throwable $e) {
                        return $default;
                    }
                };

                $chatEnabled = $moduleEnabled('chat.module_enabled');
                $documentsEnabled = $moduleEnabled('documents.module_enabled');
                $spreadsheetsEnabled = $moduleEnabled('spreadsheets.module_enabled');

                $sections = [
                    ['title' => 'SALES', 'items' => [
                        ['route' => 'dashboard',    'icon' => '◫',  'label' => 'Dashboard',      'perm' => 'view_dashboard', 'training' => 'nav-dashboard'],
                        ['route' => 'leads',        'icon' => '✏️',  'label' => 'Leads',           'perm' => 'view_leads', 'training' => 'nav-leads'],
                        ['route' => 'pipeline',     'icon' => '📈', 'label' => 'Pipeline',        'perm' => 'view_pipeline', 'training' => 'nav-pipeline'],
                        ['route' => 'deals',        'icon' => '📋', 'label' => 'Deals',           'perm' => 'view_deals', 'training' => 'nav-deals'],
                        ['route' => 'verification', 'icon' => '✓',  'label' => 'Verification',    'perm' => 'view_verification', 'training' => 'nav-verification'],
                        ['route' => 'clients',      'icon' => '💰', 'label' => 'Clients',         'perm' => 'view_all_leads', 'training' => 'nav-clients'],
                    ]],
                    ['title' => 'PERFORMANCE', 'items' => [
                        ['route' => 'stats',              'icon' => '📊', 'label' => 'Statistics',           'perm' => 'view_stats', 'training' => 'nav-stats'],
                        ['route' => 'sales-intelligence', 'icon' => '🧠', 'label' => 'Sales Intelligence',  'perm' => null, 'training' => 'nav-sales-intelligence'],
                        ['route' => 'tasks',              'icon' => '☑',  'label' => 'Task List',            'perm' => null, 'training' => 'nav-tasks'],
                        ['route' => 'payroll',            'icon' => '💵', 'label' => 'Payroll',              'perm' => 'view_payroll', 'training' => 'nav-payroll'],
                        ['route' => 'chargebacks',        'icon' => '⚠️',  'label' => 'Chargebacks',          'perm' => null, 'training' => 'nav-chargebacks'],
                    ]],
                    ['title' => 'TRAINING', 'items' => [
                        ['route' => 'sales-training','icon' => '🎯', 'label' => 'Sales Training',   'perm' => null, 'training' => 'nav-sales-training'],
                        ['route' => 'script-editor', 'icon' => '📜', 'label' => 'Script Editor',    'perm' => 'edit_users', 'training' => 'nav-script-editor'],
                        ['route' => 'daily-sales',  'icon' => '📅', 'label' => 'Daily Sales System','perm' => null, 'training' => 'nav-daily-sales'],
                        ['route' => 'training',     'icon' => '📚', 'label' => 'Training & Help',  'perm' => null, 'training' => 'nav-training'],
                    ]],
                    ['title' => 'COMMUNICATION', 'items' => [
                        // Sidebar chat disabled — use bubble chat (bottom-right)
                        ['route' => 'calls',        'icon' => '🔗', 'label' => 'Prime Connect',    'perm' => null, 'training' => 'nav-calls'],
                    ]],
                    ['title' => 'WORKSPACE', 'items' => [
                        ['route' => 'documents',    'icon' => '📄', 'label' => 'Documents',       'perm' => 'view_documents', 'enabled' => $documentsEnabled, 'training' => 'nav-documents'],
                        ['route' => 'spreadsheets', 'icon' => '🧮', 'label' => 'Spreadsheets',    'perm' => 'view_spreadsheets', 'enabled' => $spreadsheetsEnabled, 'training' => 'nav-spreadsheets'],
                        ['route' => 'tracker',      'icon' => '📅', 'label' => 'Tracker',         'perm' => null, 'training' => 'nav-tracker'],
                        ['route' => 'transfers',    'icon' => '♻️',  'label' => 'Transfers',       'perm' => null, 'training' => 'nav-transfers'],
                    ]],
                    ['title' => 'SYSTEM', 'items' => [
                        ['route' => 'users',          'icon' => '👥', 'label' => 'Users',           'perm' => 'view_users', 'training' => 'nav-users'],
                        ['route' => 'settings',       'icon' => '⚙️',  'label' => 'Settings',        'perm' => null, 'training' => 'nav-settings'],
                        ['route' => 'system-monitor', 'icon' => '📡', 'label' => 'System Monitor',  'perm' => 'master_override', 'training' => 'nav-system-monitor'],
                    ]],
                ];
            @endphp

            @foreach($sections as $section)
                @php
                    $visibleItems = collect($section['items'])->filter(function ($item) use ($user) {
                        $enabled = $item['enabled'] ?? true;
                        return $enabled && (!$item['perm'] || $user->hasPerm($item['perm']));
                    });
                @endphp
                @if($visibleItems->isNotEmpty())
                    <div class="pt-3 pb-1 px-3">
                        <span class="text-[9px] text-crm-t3 uppercase tracking-widest font-bold">{{ $section['title'] }}</span>
                    </div>
                    @foreach($visibleItems as $item)
                        @php try { $navHref = route($item['route']); } catch (\Throwable $e) { $navHref = '/' . $item['route']; } @endphp
                        <a href="{{ $navHref }}" @click="drawerOpen = false"
                           data-training="{{ $item['training'] ?? $item['route'] }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] font-medium transition
                                  {{ request()->routeIs($item['route']) ? 'bg-blue-50 text-blue-600' : 'text-crm-t2 hover:bg-crm-hover' }}">
                            <span class="w-5 text-center text-sm">{{ $item['icon'] }}</span>
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                @endif
            @endforeach
        </div>

        {{-- Drawer Footer --}}
        <div class="border-t border-crm-border p-3 flex-shrink-0">
            <div class="flex items-center gap-3 px-2 py-2">
                <div class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-semibold text-white"
                     style="background: {{ $user->color ?? '#3b82f6' }}">{{ $user->avatar ?? 'U' }}</div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-semibold truncate">{{ $user->name }}</div>
                    <div class="text-[11px] text-crm-t3 capitalize">{{ str_replace('_', ' ', $user->role) }}</div>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="mt-1">
                @csrf
                <button type="submit" class="flex items-center gap-3 w-full px-3 py-2 rounded-lg text-sm font-medium text-red-500 hover:bg-red-50 transition">
                    <span class="w-5 text-center">🚪</span>
                    Log Out
                </button>
            </form>
        </div>
    </nav>

    {{-- Main Content --}}
    {{-- Global Toast Notifications --}}
    <div x-data="{ toasts: [] }"
         x-init="
            @if(session('deal_success') || session('message') || session('success'))
                toasts.push({ msg: '{{ session('deal_success') ?: session('message') ?: session('success') }}', type: 'success' });
                setTimeout(() => toasts.shift(), 4000);
            @endif
            @if(session('deal_error') || session('error'))
                toasts.push({ msg: '{{ session('deal_error') ?: session('error') }}', type: 'error' });
                setTimeout(() => toasts.shift(), 4000);
            @endif
         "
         class="fixed top-14 right-4 z-[99998] space-y-2" style="pointer-events:none">
        <template x-for="(t, i) in toasts" :key="i">
            <div x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0"
                 :class="t.type === 'success' ? 'bg-emerald-500' : 'bg-red-500'"
                 class="flex items-center gap-2 px-5 py-3 rounded-xl shadow-lg text-white text-sm font-semibold" style="pointer-events:auto; min-width:280px">
                <span x-text="t.type === 'success' ? '✓' : '✕'" class="text-lg"></span>
                <span x-text="t.msg"></span>
            </div>
        </template>
    </div>

    <main class="pt-12">
        {{ $slot }}
    </main>

    @php
        $chatSettingEnabled = true;
        try {
            $chatRaw = \Illuminate\Support\Facades\DB::table('crm_settings')->where('key', 'chat.module_enabled')->value('value');
            if ($chatRaw !== null) {
                $chatSettingEnabled = json_decode($chatRaw, true) === true;
            }
        } catch (\Throwable $e) {}
    @endphp

    @if($chatSettingEnabled && auth()->check())
        @livewire('chat-widget')
    @endif

    {{-- Global incoming call notification — polls for pending invites on every page --}}
    @auth
        @livewire('incoming-call-alert')
    @endauth

    {{-- Global interactive training walkthrough overlay --}}
    @auth
        @livewire('training-overlay')
    @endauth

    @livewireScripts

    {{-- Presence heartbeat tracker --}}
    @auth
    <script>
    (function() {
        let lastActivity = Date.now();
        const HEARTBEAT_MS = 30000;
        const IDLE_MS = 300000;
        let heartbeatStopped = false;

        function getCsrf() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        }

        ['mousemove','mousedown','keydown','scroll','touchstart'].forEach(evt => {
            document.addEventListener(evt, () => { lastActivity = Date.now(); }, { passive: true, capture: true });
        });
        document.addEventListener('visibilitychange', () => { if (!document.hidden) lastActivity = Date.now(); });

        function sendHeartbeat() {
            if (heartbeatStopped) return;
            const isActive = (Date.now() - lastActivity) < IDLE_MS;
            fetch('/presence/heartbeat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ active: isActive }),
                keepalive: true
            }).then(r => {
                if (r.status === 419) {
                    // CSRF expired or session gone — stop spamming, reload will fix it
                    heartbeatStopped = true;
                    console.warn('Heartbeat stopped: session expired (419)');
                }
            }).catch(() => {});
        }

        sendHeartbeat();
        setInterval(sendHeartbeat, HEARTBEAT_MS);

        window.addEventListener('beforeunload', () => {
            if (heartbeatStopped) return;
            fetch('/presence/heartbeat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ active: false }),
                keepalive: true
            }).catch(() => {});
        });
    })();
    </script>
    @endauth

    {{-- Service Worker Registration --}}
    <script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    }
    </script>

    {{-- iPhone PWA Install Prompt --}}
    @auth
    <div x-data="pwaInstall()" x-show="show" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-4" class="fixed bottom-20 left-4 right-4 z-[99990] sm:left-auto sm:right-5 sm:w-[360px]" style="display:none">
        <div class="rounded-2xl overflow-hidden shadow-2xl border border-blue-200/50 bg-white">
            {{-- Header --}}
            <div class="bg-gradient-to-r from-[#0f172a] to-[#1e293b] px-5 pt-4 pb-3">
                <div class="flex items-center gap-3">
                    <img src="/icons/apple-touch-icon.png" class="w-12 h-12 rounded-xl shadow-lg" alt="CRM Prime">
                    <div>
                        <div class="text-white font-bold text-base">Install CRM Prime</div>
                        <div class="text-blue-300 text-xs mt-0.5">Use as a full-screen app</div>
                    </div>
                </div>
            </div>

            {{-- Steps --}}
            <div class="px-5 py-4 space-y-3">
                <div class="flex items-start gap-3">
                    <div class="w-7 h-7 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs font-bold flex-shrink-0">1</div>
                    <div>
                        <div class="text-sm font-semibold text-gray-800">Tap the <span class="inline-flex items-center"><svg class="w-4 h-4 text-blue-500 mx-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg></span> Share button</div>
                        <div class="text-xs text-gray-500">Bottom of Safari (or top on iPad)</div>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <div class="w-7 h-7 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs font-bold flex-shrink-0">2</div>
                    <div>
                        <div class="text-sm font-semibold text-gray-800">Tap <span class="font-bold">"Add to Home Screen"</span></div>
                        <div class="text-xs text-gray-500">Scroll down in the share menu if needed</div>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <div class="w-7 h-7 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs font-bold flex-shrink-0">3</div>
                    <div>
                        <div class="text-sm font-semibold text-gray-800">Tap <span class="font-bold">"Add"</span></div>
                        <div class="text-xs text-gray-500">CRM Prime will appear on your Home Screen</div>
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="border-t border-gray-100 px-5 py-3 bg-gray-50 flex items-center justify-between">
                <button @click="dismiss('later')" class="text-xs text-gray-400 hover:text-gray-600 font-medium transition">Remind me later</button>
                <button @click="dismiss('never')" class="px-4 py-1.5 text-xs font-bold text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition">Got it</button>
            </div>
        </div>
    </div>
    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('pwaInstall', () => ({
            show: false,
            init() {
                // Only show on iOS Safari, not already in standalone mode
                const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
                const isStandalone = window.navigator.standalone === true || window.matchMedia('(display-mode: standalone)').matches;
                const dismissed = localStorage.getItem('pwa_install_dismissed');

                if (isIOS && !isStandalone && dismissed !== 'never') {
                    // If dismissed as "later", show again after 3 days
                    if (dismissed) {
                        const ts = parseInt(dismissed, 10);
                        if (Date.now() - ts < 3 * 24 * 60 * 60 * 1000) return;
                    }
                    // Delay showing so it doesn't flash during page load
                    setTimeout(() => { this.show = true; }, 2000);
                }
            },
            dismiss(type) {
                this.show = false;
                if (type === 'never') {
                    localStorage.setItem('pwa_install_dismissed', 'never');
                } else {
                    localStorage.setItem('pwa_install_dismissed', String(Date.now()));
                }
            }
        }));
    });
    </script>
    @endauth
</body>
</html>
