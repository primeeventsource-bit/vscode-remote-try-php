<div wire:poll.10s>
    @php
        $hasInvite = ($meetingInvite ?? null) || (($invite ?? null) && !($meetingInvite ?? null));
        if ($meetingInvite ?? null) {
            $mtg = $meetingInvite->meeting;
            $caller = $mtg?->host;
            $callerName = $caller?->name ?? 'Unknown';
            $callerAvatar = $caller?->avatar ?? substr($callerName, 0, 2);
            $callerColor = $caller?->color ?? '#6366f1';
            $callType = $mtg?->type === 'direct' ? 'Call' : 'Meeting';
            $callTitle = $mtg?->title ?? 'Direct Call';
            $acceptUrl = route('meeting-room', ['uuid' => $mtg?->uuid]);
            $declineAction = "declineMeetingInvite({$meetingInvite->id})";
            $isVideo = true;
        } elseif ($invite ?? null) {
            $room = $invite->room;
            $caller = $room?->creator;
            $callerName = $caller?->name ?? 'Unknown';
            $callerAvatar = $caller?->avatar ?? substr($callerName, 0, 2);
            $callerColor = $caller?->color ?? '#6366f1';
            $callType = 'Video Call';
            $callTitle = $room?->name ?? 'Video Call';
            $acceptUrl = route('video-call', ['room' => $room?->uuid]);
            $declineAction = "declineOldInvite({$invite->id})";
            $isVideo = true;
        }
    @endphp

    @if($hasInvite)
    <div class="fixed top-5 right-5 z-[99999] w-[340px]" x-data="{ show: true }" x-show="show" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-[-20px] scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100">
        <style>
            @keyframes pc-ring-pulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(99,102,241,0.5), 0 0 30px rgba(99,102,241,0.15); } 50% { box-shadow: 0 0 0 12px rgba(99,102,241,0), 0 0 40px rgba(99,102,241,0.25); } }
            @keyframes pc-avatar-glow { 0%, 100% { box-shadow: 0 0 0 0 rgba(16,185,129,0.4); } 50% { box-shadow: 0 0 0 8px rgba(16,185,129,0); } }
            @keyframes pc-wave { 0% { transform: scaleY(0.4); } 50% { transform: scaleY(1); } 100% { transform: scaleY(0.4); } }
        </style>

        <div class="rounded-2xl overflow-hidden shadow-2xl shadow-pc-primary/30 border border-pc-primary/30 backdrop-blur-sm" style="animation: pc-ring-pulse 2s ease-in-out infinite;">
            {{-- Header --}}
            <div class="bg-gradient-to-r from-pc-dark to-pc-surface px-5 pt-4 pb-3">
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-5 h-5 rounded-lg bg-gradient-to-br from-pc-primary to-pc-accent flex items-center justify-center">
                        <span class="text-[10px]">🔗</span>
                    </div>
                    <span class="text-[11px] font-bold uppercase tracking-widest text-pc-primary">Prime Connect</span>
                </div>

                <div class="flex items-center gap-4">
                    {{-- Caller Avatar --}}
                    <div class="relative">
                        @if($caller?->avatar_path)
                            <img src="{{ asset('storage/' . $caller->avatar_path) }}" class="w-14 h-14 rounded-full object-cover ring-2 ring-pc-live/50" style="animation: pc-avatar-glow 2s ease-in-out infinite;">
                        @else
                            <div class="w-14 h-14 rounded-full flex items-center justify-center text-lg font-bold text-white ring-2 ring-pc-live/50" style="background: {{ $callerColor }}; animation: pc-avatar-glow 2s ease-in-out infinite;">{{ $callerAvatar }}</div>
                        @endif
                        <span class="absolute -bottom-0.5 -right-0.5 w-4 h-4 rounded-full bg-pc-live border-2 border-pc-dark flex items-center justify-center">
                            <svg class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        </span>
                    </div>

                    {{-- Call Info --}}
                    <div class="flex-1 min-w-0">
                        <div class="text-white font-bold text-base truncate">{{ $callerName }}</div>
                        <div class="text-pc-muted text-xs mt-0.5">Incoming {{ $callType }}</div>
                        <div class="flex items-center gap-1.5 mt-1.5">
                            {{-- Audio waveform animation --}}
                            <div class="flex items-end gap-[2px] h-3">
                                @for($i = 0; $i < 4; $i++)
                                    <div class="w-[3px] bg-pc-live rounded-full" style="animation: pc-wave 1.2s ease-in-out {{ $i * 0.15 }}s infinite; height: 100%;"></div>
                                @endfor
                            </div>
                            <span class="text-pc-live text-[11px] font-semibold">Ringing...</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="flex">
                <a href="{{ $acceptUrl }}" class="flex-1 py-3.5 text-center text-sm font-bold text-white bg-pc-live hover:brightness-110 transition flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    Accept
                </a>
                <button wire:click="{{ $declineAction }}" class="flex-1 py-3.5 text-center text-sm font-bold text-pc-end bg-pc-dark hover:bg-pc-surface transition border-l border-white/10 flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    Decline
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
