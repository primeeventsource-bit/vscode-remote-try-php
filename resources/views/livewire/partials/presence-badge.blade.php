{{-- @include('livewire.partials.presence-badge', ['user' => $user, 'showLabel' => true]) --}}
@if(isset($user) && $user)
    @php
        $ps = $user->presence_status ?? 'offline';
        $dotClass = match($ps) {
            'online' => 'bg-emerald-500',
            'idle' => 'bg-amber-400',
            default => 'bg-gray-400',
        };
        $showLabel = $showLabel ?? true;
        $label = match($ps) {
            'online' => 'Active',
            'idle' => 'Idle' . (method_exists($user, 'formattedIdleDuration') ? ' ' . $user->formattedIdleDuration() : ''),
            default => $user->last_active_at ? 'Last seen ' . $user->last_active_at->diffForHumans() : 'Offline',
        };
        $textClass = match($ps) {
            'online' => 'text-emerald-600',
            'idle' => 'text-amber-600',
            default => 'text-gray-400',
        };
    @endphp
    <span class="inline-flex items-center gap-1" title="{{ $label }}">
        <span class="w-2 h-2 rounded-full {{ $dotClass }} flex-shrink-0 {{ $ps === 'online' ? 'animate-pulse' : '' }}"></span>
        @if($showLabel)
            <span class="text-[9px] {{ $textClass }}">{{ $label }}</span>
        @endif
    </span>
@endif
