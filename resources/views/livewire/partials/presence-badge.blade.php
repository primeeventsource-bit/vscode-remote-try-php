{{-- @include('livewire.partials.presence-badge', ['user' => $user]) --}}
@if(isset($user) && $user)
    @php
        $ps = $user->presence_status ?? 'offline';
        $dotClass = match($ps) {
            'online' => 'bg-emerald-500',
            'idle' => 'bg-amber-400',
            default => 'bg-red-400',
        };
        $label = match($ps) {
            'online' => 'Online',
            'idle' => 'Idle ' . ($user->formattedIdleDuration ? $user->formattedIdleDuration() : ''),
            default => 'Offline',
        };
    @endphp
    <span class="inline-flex items-center gap-1">
        <span class="w-2 h-2 rounded-full {{ $dotClass }} flex-shrink-0"></span>
        <span class="text-[9px] {{ $ps === 'online' ? 'text-emerald-600' : ($ps === 'idle' ? 'text-amber-600' : 'text-red-400') }}">{{ $label }}</span>
    </span>
@endif
