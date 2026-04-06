<div wire:poll.10s>
    {{-- New unified meeting invites --}}
    @if($meetingInvite ?? null)
        @php
            $mtg = $meetingInvite->meeting;
            $caller = $mtg?->host;
            $callerName = $caller?->name ?? 'Unknown';
            $callerAvatar = $caller?->avatar ?? substr($callerName, 0, 2);
            $callerColor = $caller?->color ?? '#3b82f6';
            $callType = $mtg?->type === 'direct' ? 'Video Call' : 'Meeting';
        @endphp
        <div class="fixed top-4 right-4 z-[99999] w-80 animate-bounce" style="animation-duration: 2s;">
            <div class="bg-white border-2 border-blue-500 rounded-2xl shadow-2xl overflow-hidden">
                <div class="px-4 pt-4 pb-2 flex items-center gap-3">
                    @if($caller?->avatar_path)
                        <img src="{{ asset('storage/' . $caller->avatar_path) }}" class="w-12 h-12 rounded-full object-cover ring-2 ring-blue-400">
                    @else
                        <div class="w-12 h-12 rounded-full flex items-center justify-center text-sm font-bold text-white ring-2 ring-blue-400" style="background: {{ $callerColor }}">{{ $callerAvatar }}</div>
                    @endif
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-bold text-gray-900">Incoming {{ $callType }}</div>
                        <div class="text-xs text-gray-600 truncate">{{ $callerName }}</div>
                        <div class="text-[10px] text-gray-400">{{ $mtg?->title ?? 'Direct Call' }}</div>
                    </div>
                </div>
                <div class="px-4 pb-2"><div class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span><span class="text-[10px] text-gray-500 font-medium">Ringing...</span></div></div>
                <div class="flex border-t border-gray-100">
                    <a href="{{ route('meeting-room', ['uuid' => $mtg?->uuid]) }}" class="flex-1 py-3 text-center text-sm font-bold text-white bg-emerald-500 hover:bg-emerald-600 transition">Accept</a>
                    <button wire:click="declineMeetingInvite({{ $meetingInvite->id }})" class="flex-1 py-3 text-center text-sm font-bold text-red-600 bg-red-50 hover:bg-red-100 transition border-l border-gray-100">Decline</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Old video_room invites (backward compat) --}}
    @if(($invite ?? null) && !($meetingInvite ?? null))
        @php
            $room = $invite->room;
            $caller = $room?->creator;
            $callerName = $caller?->name ?? 'Unknown';
            $callerAvatar = $caller?->avatar ?? substr($callerName, 0, 2);
            $callerColor = $caller?->color ?? '#3b82f6';
            $roomUuid = $room?->uuid;
        @endphp
        <div class="fixed top-4 right-4 z-[99999] w-80 animate-bounce" style="animation-duration: 2s;">
            <div class="bg-white border-2 border-blue-500 rounded-2xl shadow-2xl overflow-hidden">
                <div class="px-4 pt-4 pb-2 flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center text-sm font-bold text-white ring-2 ring-blue-400" style="background: {{ $callerColor }}">{{ $callerAvatar }}</div>
                    <div class="flex-1"><div class="text-sm font-bold text-gray-900">Incoming Video Call</div><div class="text-xs text-gray-600">{{ $callerName }}</div></div>
                </div>
                <div class="px-4 pb-2"><span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse inline-block"></span> <span class="text-[10px] text-gray-500">Ringing...</span></div>
                <div class="flex border-t border-gray-100">
                    <a href="{{ route('video-call', ['room' => $roomUuid]) }}" class="flex-1 py-3 text-center text-sm font-bold text-white bg-emerald-500 hover:bg-emerald-600 transition">Accept</a>
                    <button wire:click="declineOldInvite({{ $invite->id }})" class="flex-1 py-3 text-center text-sm font-bold text-red-600 bg-red-50 hover:bg-red-100 transition border-l border-gray-100">Decline</button>
                </div>
            </div>
        </div>
    @endif
</div>
