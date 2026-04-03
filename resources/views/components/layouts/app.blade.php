<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Prime CRM' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('build/app.css') }}">
    <style>[x-cloak]{display:none!important}</style>
    @livewireStyles
</head>
<body class="bg-crm-bg font-sans text-crm-t1 min-h-screen" x-data="{ drawerOpen: false }">

    {{-- Top Bar --}}
    <header class="fixed top-0 left-0 right-0 h-12 bg-crm-surface border-b border-crm-border flex items-center px-4 z-40">
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
                <div class="w-8 h-8 bg-white border border-crm-border rounded-lg flex items-center justify-center">
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

                $nav = collect([
                    ['route' => 'dashboard',    'icon' => '◫',  'label' => 'Dashboard',      'perm' => 'view_dashboard'],
                    ['route' => 'chargebacks',  'icon' => '⚠️',  'label' => 'Chargebacks',     'perm' => null],
                    ['route' => 'stats',        'icon' => '📊', 'label' => 'Statistics',      'perm' => 'view_stats'],
                    ['route' => 'leads',        'icon' => '✏️',  'label' => 'Leads',           'perm' => 'view_leads'],
                    ['route' => 'pipeline',     'icon' => '📈', 'label' => 'Pipeline',        'perm' => 'view_pipeline'],
                    ['route' => 'deals',        'icon' => '📋', 'label' => 'Deals',           'perm' => 'view_deals'],
                    ['route' => 'verification', 'icon' => '✓',  'label' => 'Verification',    'perm' => 'view_verification'],
                    ['route' => 'clients',      'icon' => '💰', 'label' => 'Clients',         'perm' => 'view_all_leads'],
                    ['route' => 'tasks',        'icon' => '☑',  'label' => 'Tasks',           'perm' => null],
                    ['route' => 'tracker',      'icon' => '📅', 'label' => 'Tracker',         'perm' => null],
                    ['route' => 'transfers',    'icon' => '♻️',  'label' => 'Transfers',       'perm' => null],
                    ['route' => 'payroll',      'icon' => '💵', 'label' => 'Payroll',         'perm' => 'view_payroll'],
                    ['route' => 'documents',    'icon' => '📄', 'label' => 'Documents',       'perm' => 'view_documents', 'enabled' => $documentsEnabled],
                    ['route' => 'spreadsheets', 'icon' => '🧮', 'label' => 'Spreadsheets',    'perm' => 'view_spreadsheets', 'enabled' => $spreadsheetsEnabled],
                    ['route' => 'users',        'icon' => '👥', 'label' => 'Users',           'perm' => 'view_users'],
                ])->filter(function ($item) use ($user) {
                    $enabled = $item['enabled'] ?? true;
                    return $enabled && (!$item['perm'] || $user->hasPerm($item['perm']));
                });
            @endphp

            @foreach($nav as $item)
                <a href="{{ route($item['route']) }}" @click="drawerOpen = false"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition
                          {{ request()->routeIs($item['route']) ? 'bg-blue-50 text-blue-600 border border-blue-100' : 'text-crm-t2 hover:bg-crm-hover' }}">
                    <span class="w-5 text-center text-base">{{ $item['icon'] }}</span>
                    {{ $item['label'] }}
                </a>
            @endforeach

            @if($user->hasRole('master_admin'))
                <div class="border-t border-crm-border my-2"></div>
                <a href="{{ route('settings') }}" @click="drawerOpen = false"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition
                          {{ request()->routeIs('settings') ? 'bg-blue-50 text-blue-600 border border-blue-100' : 'text-crm-t2 hover:bg-crm-hover' }}">
                    <span class="w-5 text-center text-base">⚙️</span>
                    Settings
                </a>
            @endif
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

    @livewireScripts
</body>
</html>
