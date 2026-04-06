<div wire:poll.3s>
    @if($invite)
        @php
            $room = $invite->room;
            $caller = $room?->creator;
            $callerName = $caller?->name ?? 'Unknown';
            $callerAvatar = $caller?->avatar ?? substr($callerName, 0, 2);
            $callerColor = $caller?->color ?? '#3b82f6';
            $isVideo = ($room?->media_mode ?? 'video') !== 'audio';
            $callType = $isVideo ? 'Video' : 'Audio';
            $roomUuid = $room?->uuid;
        @endphp

        {{-- Incoming Call Toast — fixed position, visible on every page --}}
        <div class="fixed top-4 right-4 z-[99999] w-80 animate-bounce" style="animation-duration: 2s;">
            <div class="bg-white border-2 {{ $isVideo ? 'border-blue-500' : 'border-emerald-500' }} rounded-2xl shadow-2xl overflow-hidden">
                {{-- Header --}}
                <div class="px-4 pt-4 pb-2 flex items-center gap-3">
                    <div class="relative">
                        @if($caller?->avatar_path)
                            <img src="{{ asset('storage/' . $caller->avatar_path) }}" class="w-12 h-12 rounded-full object-cover ring-2 {{ $isVideo ? 'ring-blue-400' : 'ring-emerald-400' }}">
                        @else
                            <div class="w-12 h-12 rounded-full flex items-center justify-center text-sm font-bold text-white ring-2 {{ $isVideo ? 'ring-blue-400' : 'ring-emerald-400' }}" style="background: {{ $callerColor }}">
                                {{ $callerAvatar }}
                            </div>
                        @endif
                        <span class="absolute -bottom-0.5 -right-0.5 text-lg">{{ $isVideo ? '📹' : '📞' }}</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-bold text-gray-900">Incoming {{ $callType }} Call</div>
                        <div class="text-xs text-gray-600 truncate">{{ $callerName }}</div>
                        <div class="text-[10px] text-gray-400 mt-0.5">{{ $room?->name ?? 'Direct Call' }}</div>
                    </div>
                </div>

                {{-- Ringing indicator --}}
                <div class="px-4 pb-2">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="text-[10px] text-gray-500 font-medium">Ringing...</span>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="flex border-t border-gray-100">
                    <a href="{{ route('video-call', ['room' => $roomUuid]) }}"
                       class="flex-1 py-3 text-center text-sm font-bold text-white {{ $isVideo ? 'bg-blue-500 hover:bg-blue-600' : 'bg-emerald-500 hover:bg-emerald-600' }} transition">
                        Answer
                    </a>
                    <button wire:click="declineInvite({{ $invite->id }})"
                        class="flex-1 py-3 text-center text-sm font-bold text-red-600 bg-red-50 hover:bg-red-100 transition border-l border-gray-100">
                        Decline
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
