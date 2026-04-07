<div class="p-5">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-bold">Prime Connect</h2>
            <p class="text-xs text-crm-t3 mt-0.5">Voice calls, video meetings, and call history</p>
        </div>
        <button wire:click="$set('showCreate', true)" class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">+ New Call</button>
    </div>

    {{-- Pending Invites Banner --}}
    @if($pendingInvites->isNotEmpty())
        <div class="mb-4 space-y-2">
            @foreach($pendingInvites as $invite)
                <div class="flex items-center justify-between bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 animate-pulse" style="animation-duration: 3s;">
                    <div class="flex items-center gap-3">
                        <span class="text-lg">{{ $invite->meeting?->type === 'direct' ? '📞' : '📹' }}</span>
                        <div>
                            <div class="text-sm font-bold">{{ $invite->meeting?->title ?? 'Incoming Call' }}</div>
                            <div class="text-xs text-crm-t3">From {{ $invite->meeting?->host?->name ?? 'Unknown' }}</div>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <a href="{{ route('meeting-room', ['uuid' => $invite->meeting?->uuid]) }}" class="px-4 py-2 text-xs font-bold text-white bg-emerald-500 rounded-lg hover:bg-emerald-600 transition">Accept</a>
                        <button wire:click="$wire" class="px-3 py-2 text-xs font-semibold text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition">Decline</button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Tabs --}}
    <div class="flex gap-1 bg-crm-card border border-crm-border rounded-lg p-0.5 mb-5">
        @foreach(['active' => 'Active (' . $activeMeetings->count() . ')', 'past' => 'History', 'create' => '+ Create'] as $k => $l)
            <button wire:click="$set('tab', '{{ $k }}')" class="px-4 py-1.5 text-xs font-semibold rounded-md transition {{ $tab === $k ? 'bg-white text-blue-600 shadow-sm' : 'text-crm-t3 hover:text-crm-t1' }}">{{ $l }}</button>
        @endforeach
    </div>

    {{-- Create Call Modal --}}
    @if($showCreate || $tab === 'create')
        <div class="bg-crm-card border border-crm-border rounded-lg p-6 mb-5">
            <h3 class="text-lg font-bold mb-4">Start a Call</h3>
            <div class="space-y-4">
                {{-- Call Type --}}
                <div>
                    <label class="text-xs text-crm-t3 uppercase font-semibold">Call Type</label>
                    <div class="flex gap-2 mt-1">
                        <button wire:click="$set('callType', 'video')"
                            class="flex-1 px-4 py-3 rounded-lg border-2 text-sm font-semibold transition {{ $callType === 'video' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-crm-border bg-white text-crm-t2 hover:bg-crm-hover' }}">
                            📹 Video Call
                        </button>
                        <button wire:click="$set('callType', 'voice')"
                            class="flex-1 px-4 py-3 rounded-lg border-2 text-sm font-semibold transition {{ $callType === 'voice' ? 'border-emerald-500 bg-emerald-50 text-emerald-700' : 'border-crm-border bg-white text-crm-t2 hover:bg-crm-hover' }}">
                            📞 Voice Call
                        </button>
                    </div>
                </div>

                {{-- Meeting Name --}}
                <div>
                    <label for="fld-call-name" class="text-xs text-crm-t3 uppercase font-semibold">Call Name (optional)</label>
                    <input id="fld-call-name" wire:model="meetingName" type="text" placeholder="e.g., Team Standup, Client Follow-up..."
                        class="w-full mt-1 px-3 py-2 text-sm bg-white border border-crm-border rounded-lg focus:border-blue-400 focus:outline-none">
                </div>

                {{-- Invite Participants --}}
                <div>
                    <label class="text-xs text-crm-t3 uppercase font-semibold">Invite Participants</label>
                    <div class="max-h-48 overflow-y-auto border border-crm-border rounded-lg mt-1 bg-white">
                        @foreach($allUsers as $u)
                            @if($u->id !== auth()->id())
                                <label class="flex items-center gap-3 px-3 py-2 border-b border-crm-border last:border-0 hover:bg-crm-hover cursor-pointer">
                                    <input id="fld-invite-{{ $u->id }}" type="checkbox" wire:model="invitedUserIds" value="{{ $u->id }}" class="h-4 w-4 rounded">
                                    <div class="w-6 h-6 rounded-full flex items-center justify-center text-[8px] font-bold text-white" style="background: {{ $u->color ?? '#6b7280' }}">{{ $u->avatar ?? substr($u->name, 0, 2) }}</div>
                                    <span class="text-sm">{{ $u->name }}</span>
                                    <span class="text-[10px] text-crm-t3 ml-auto capitalize">{{ str_replace('_', ' ', $u->role) }}</span>
                                </label>
                            @endif
                        @endforeach
                    </div>
                </div>

                <div class="flex gap-3">
                    <button wire:click="$set('showCreate', false); $set('tab', 'active')" class="flex-1 px-4 py-2 text-sm font-semibold text-crm-t2 bg-gray-100 rounded-lg hover:bg-gray-200 transition">Cancel</button>
                    <button wire:click="createCall" class="flex-1 px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition"
                        wire:loading.attr="disabled" wire:loading.class="opacity-50">
                        <span wire:loading.remove wire:target="createCall">Start {{ $callType === 'video' ? 'Video' : 'Voice' }} Call</span>
                        <span wire:loading wire:target="createCall">Starting...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Active Calls --}}
    @if($tab === 'active')
        @forelse($activeMeetings as $meeting)
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 mb-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl {{ $meeting->isLive() ? 'bg-emerald-500' : 'bg-amber-500' }} flex items-center justify-center text-white text-lg">
                            {{ $meeting->isLive() ? '🟢' : '⏳' }}
                        </div>
                        <div>
                            <div class="text-sm font-bold">{{ $meeting->title ?? 'Call' }}</div>
                            <div class="text-xs text-crm-t3">
                                {{ $meeting->host?->name ?? 'Unknown' }}
                                · {{ $meeting->isLive() ? 'Live' : ($meeting->isRinging() ? 'Ringing' : 'Waiting') }}
                                · {{ $meeting->activeParticipants()->count() }} in call
                                · {{ ucfirst($meeting->type) }}
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <a href="{{ route('meeting-room', ['uuid' => $meeting->uuid]) }}" class="px-4 py-2 text-xs font-semibold text-white bg-emerald-500 rounded-lg hover:bg-emerald-600 transition">Join</a>
                        @if($meeting->host_user_id === auth()->id() || $isAdmin)
                            <button wire:click="endMeeting({{ $meeting->id }})" wire:confirm="End this call for all participants?" class="px-3 py-2 text-xs font-semibold text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition">End</button>
                        @endif
                    </div>
                </div>

                {{-- Participant Avatars --}}
                <div class="flex items-center gap-1 mt-3">
                    @foreach($meeting->participants->take(8) as $p)
                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-[8px] font-bold text-white border-2 border-white -ml-1 first:ml-0" style="background: {{ $p->user?->color ?? '#6b7280' }}" title="{{ $p->user?->name }} ({{ $p->invite_status }})">
                            {{ $p->user?->avatar ?? substr($p->user?->name ?? '?', 0, 2) }}
                        </div>
                    @endforeach
                    @if($meeting->participants->count() > 8)
                        <span class="text-[10px] text-crm-t3 ml-1">+{{ $meeting->participants->count() - 8 }}</span>
                    @endif
                </div>
            </div>
        @empty
            <div class="bg-crm-card border border-crm-border rounded-lg p-12 text-center">
                <div class="text-3xl mb-2">📞</div>
                <div class="text-sm font-bold mb-1">No active calls</div>
                <p class="text-xs text-crm-t3">Start a new call or wait for an invite</p>
            </div>
        @endforelse
    @endif

    {{-- Past Calls --}}
    @if($tab === 'past')
        @forelse($pastMeetings as $meeting)
            <div class="bg-crm-card border border-crm-border rounded-lg px-4 py-3 mb-2 flex items-center justify-between">
                <div>
                    <div class="text-sm font-semibold">{{ $meeting->title ?? 'Call' }}</div>
                    <div class="text-[10px] text-crm-t3">
                        {{ $meeting->host?->name }}
                        · {{ $meeting->started_at?->format('M j, g:i A') ?? 'Not started' }}
                        @if($meeting->ended_at && $meeting->started_at)
                            · {{ $meeting->started_at->diffForHumans($meeting->ended_at, true) }}
                        @endif
                        · {{ ucfirst($meeting->type) }}
                    </div>
                </div>
                <span class="text-[9px] font-bold px-2 py-0.5 rounded {{ match($meeting->status) {
                    'ended' => 'bg-gray-100 text-gray-500',
                    'missed' => 'bg-amber-50 text-amber-600',
                    'declined' => 'bg-red-50 text-red-500',
                    'failed' => 'bg-red-100 text-red-600',
                    default => 'bg-gray-100 text-gray-500',
                } }}">{{ ucfirst($meeting->status) }}</span>
            </div>
        @empty
            <p class="text-xs text-crm-t3 text-center py-8">No call history</p>
        @endforelse
    @endif
</div>
