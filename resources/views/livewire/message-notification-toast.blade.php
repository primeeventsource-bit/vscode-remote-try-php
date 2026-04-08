<div wire:poll.5s="checkNewMessages">
    @if(!empty($toasts))
    <div class="fixed top-14 left-4 right-4 sm:left-auto sm:right-4 sm:w-[340px] z-[99997] space-y-2" style="pointer-events:none;">
        @foreach($toasts as $toast)
        <div class="bg-white rounded-xl shadow-2xl border border-crm-border overflow-hidden" style="pointer-events:auto; animation: pc-fade-in 0.3s ease-out both;">
            <div class="flex items-start gap-3 p-3">
                <div class="w-9 h-9 rounded-full flex items-center justify-center text-[10px] font-bold text-white flex-shrink-0" style="background: {{ $toast['color'] }}">
                    {{ $toast['avatar'] }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-xs font-bold text-crm-t1 truncate">{{ $toast['title'] }}</div>
                    <div class="text-[11px] text-crm-t3 mt-0.5 truncate">
                        @if(!$toast['is_dm'])
                            <span class="font-semibold">{{ $toast['sender'] }}:</span>
                        @endif
                        {{ $toast['body'] }}
                    </div>
                </div>
                <button wire:click="dismissToast({{ $toast['id'] }})" class="text-crm-t3 hover:text-crm-t1 text-sm flex-shrink-0 mt-0.5">&times;</button>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>
