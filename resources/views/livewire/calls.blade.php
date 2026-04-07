<div class="p-5">
    <style>
        @keyframes pc-gradient-shift { 0%, 100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }
        @keyframes pc-fade-in { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes pc-live-dot { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: .6; transform: scale(1.3); } }
        .pc-card-enter { animation: pc-fade-in 0.3s ease-out both; }
        .pc-live-pulse { animation: pc-live-dot 1.5s ease-in-out infinite; }
    </style>
    {{-- Prime Connect Header --}}
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-pc-primary to-pc-accent shadow-lg shadow-pc-primary/20 flex items-center justify-center">
                <span class="text-lg">🔗</span>
            </div>
            <div>
                <h2 class="text-xl font-extrabold tracking-tight">
                    <span class="bg-gradient-to-r from-pc-primary to-pc-accent bg-clip-text text-transparent">Prime</span>
                    <span class="text-crm-t1">Connect</span>
                </h2>
                <p class="text-[11px] text-crm-t3 mt-0.5">Video meetings, voice calls & call history</p>
            </div>
        </div>
        <button wire:click="switchToCreate"
            class="px-5 py-2.5 text-xs font-bold text-white bg-gradient-to-r from-pc-primary to-pc-accent rounded-xl hover:shadow-lg hover:shadow-pc-primary/30 hover:-translate-y-0.5 transition-all duration-200 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            New Call
        </button>
    </div>

    {{-- Twilio Config Warning --}}
    @if($isAdmin && !($twilioConfigured ?? true))
        <div class="mb-4 px-4 py-3 bg-pc-ring/10 border border-pc-ring/30 rounded-xl flex items-center gap-3">
            <svg class="w-5 h-5 text-pc-ring flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
            <div>
                <div class="text-sm font-bold text-pc-ring">Twilio credentials not configured</div>
                <div class="text-xs text-crm-t3 mt-0.5">Set TWILIO_ACCOUNT_SID, TWILIO_API_KEY_SID, and TWILIO_API_KEY_SECRET in your environment to enable video/voice calls.</div>
            </div>
        </div>
    @endif

    {{-- Pending Invites --}}
    @if($pendingInvites->isNotEmpty())
        <div class="mb-5 space-y-2">
            @foreach($pendingInvites as $invite)
                <div class="flex items-center justify-between bg-gradient-to-r from-pc-primary/5 to-pc-accent/5 border border-pc-primary/20 rounded-xl px-4 py-3 pc-card-enter">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-pc-primary to-pc-accent flex items-center justify-center text-white">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        </div>
                        <div>
                            <div class="text-sm font-bold text-crm-t1">{{ $invite->meeting?->title ?? 'Incoming Call' }}</div>
                            <div class="text-xs text-crm-t3">From {{ $invite->meeting?->host?->name ?? 'Unknown' }}</div>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <a href="{{ route('meeting-room', ['uuid' => $invite->meeting?->uuid]) }}" class="px-4 py-2 text-xs font-bold text-white bg-pc-live rounded-lg hover:brightness-110 transition">Accept</a>
                        <button wire:click="declineInvite({{ $invite->id }})" class="px-3 py-2 text-xs font-semibold text-pc-end bg-pc-end/10 rounded-lg hover:bg-pc-end/20 transition">Decline</button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Tabs --}}
    <div class="flex gap-1 bg-crm-surface border border-crm-border rounded-xl p-1 mb-5">
        <button wire:click="switchTab('active')"
            class="px-4 py-2 text-xs font-semibold rounded-lg transition-all duration-200
            {{ $tab === 'active' ? 'bg-gradient-to-r from-pc-primary to-pc-accent text-white shadow-sm' : 'text-crm-t3 hover:text-crm-t1 hover:bg-crm-hover' }}">
            Active ({{ $activeMeetings->count() }})
        </button>
        <button wire:click="switchTab('past')"
            class="px-4 py-2 text-xs font-semibold rounded-lg transition-all duration-200
            {{ $tab === 'past' ? 'bg-gradient-to-r from-pc-primary to-pc-accent text-white shadow-sm' : 'text-crm-t3 hover:text-crm-t1 hover:bg-crm-hover' }}">
            History
        </button>
        <button wire:click="switchToCreate"
            class="px-4 py-2 text-xs font-semibold rounded-lg transition-all duration-200
            {{ $tab === 'create' ? 'bg-gradient-to-r from-pc-primary to-pc-accent text-white shadow-sm' : 'text-crm-t3 hover:text-crm-t1 hover:bg-crm-hover' }}">
            + Create
        </button>
    </div>

    {{-- Create Call Panel --}}
    @if($showCreate || $tab === 'create')
        <div class="bg-white border border-crm-border rounded-2xl overflow-hidden mb-5 shadow-sm pc-card-enter">
            <div class="bg-gradient-to-r from-pc-dark to-pc-surface px-6 py-4">
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-pc-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    Start a Call
                </h3>
            </div>
            <div class="p-6 space-y-5">
                {{-- Call Type --}}
                <div>
                    <label class="text-[10px] text-crm-t3 uppercase tracking-widest font-bold">Call Type</label>
                    <div class="flex gap-3 mt-2">
                        <button wire:click="$set('callType', 'video')"
                            class="flex-1 px-4 py-4 rounded-xl border-2 text-sm font-bold transition-all duration-200
                            {{ $callType === 'video' ? 'border-pc-primary bg-pc-primary/5 text-pc-primary shadow-sm' : 'border-crm-border bg-white text-crm-t2 hover:bg-crm-hover' }}">
                            <div class="text-2xl mb-1">📹</div>
                            Video Call
                        </button>
                        <button wire:click="$set('callType', 'voice')"
                            class="flex-1 px-4 py-4 rounded-xl border-2 text-sm font-bold transition-all duration-200
                            {{ $callType === 'voice' ? 'border-pc-live bg-pc-live/5 text-pc-live shadow-sm' : 'border-crm-border bg-white text-crm-t2 hover:bg-crm-hover' }}">
                            <div class="text-2xl mb-1">📞</div>
                            Voice Call
                        </button>
                    </div>
                </div>

                {{-- Call Name --}}
                <div>
                    <label for="fld-call-name" class="text-[10px] text-crm-t3 uppercase tracking-widest font-bold">Call Name (optional)</label>
                    <input id="fld-call-name" wire:model="meetingName" type="text" placeholder="e.g., Team Standup, Client Follow-up..."
                        class="w-full mt-1 px-4 py-2.5 text-sm bg-crm-surface border border-crm-border rounded-xl focus:border-pc-primary focus:outline-none focus:ring-2 focus:ring-pc-primary/20 transition">
                </div>

                {{-- Participants --}}
                <div>
                    <label class="text-[10px] text-crm-t3 uppercase tracking-widest font-bold">Invite Participants</label>
                    <div class="max-h-52 overflow-y-auto border border-crm-border rounded-xl mt-1 bg-white">
                        @foreach($allUsers as $u)
                            @if($u->id !== auth()->id())
                                <label class="flex items-center gap-3 px-4 py-2.5 border-b border-crm-border last:border-0 hover:bg-pc-primary/5 cursor-pointer transition">
                                    <input id="fld-invite-{{ $u->id }}" type="checkbox" wire:model="invitedUserIds" value="{{ $u->id }}"
                                        class="h-4 w-4 rounded border-crm-border text-pc-primary focus:ring-pc-primary/20">
                                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-[9px] font-bold text-white" style="background: {{ $u->color ?? '#6b7280' }}">{{ $u->avatar ?? substr($u->name, 0, 2) }}</div>
                                    <span class="text-sm font-medium">{{ $u->name }}</span>
                                    <span class="text-[10px] text-crm-t3 ml-auto capitalize">{{ str_replace('_', ' ', $u->role) }}</span>
                                </label>
                            @endif
                        @endforeach
                    </div>
                </div>

                <div class="flex gap-3 pt-1">
                    <button wire:click="cancelCreate"
                        class="flex-1 px-4 py-2.5 text-sm font-semibold text-crm-t2 bg-crm-surface rounded-xl hover:bg-crm-hover transition">Cancel</button>
                    <button wire:click="createCall"
                        class="flex-1 px-4 py-2.5 text-sm font-bold text-white bg-gradient-to-r from-pc-primary to-pc-accent rounded-xl hover:shadow-lg hover:shadow-pc-primary/20 transition-all duration-200"
                        wire:loading.attr="disabled" wire:loading.class="opacity-50">
                        <span wire:loading.remove wire:target="createCall">Start {{ $callType === 'video' ? 'Video' : 'Voice' }} Call</span>
                        <span wire:loading wire:target="createCall">Connecting...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Active Sessions --}}
    @if($tab === 'active')
        @forelse($activeMeetings as $idx => $meeting)
            <div class="bg-white border border-crm-border rounded-xl p-4 mb-3 hover:shadow-md hover:border-pc-primary/30 transition-all duration-200 pc-card-enter" style="animation-delay: {{ $idx * 0.05 }}s">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-xl flex items-center justify-center text-white shadow-sm
                            {{ $meeting->isLive() ? 'bg-gradient-to-br from-pc-live to-emerald-600' : 'bg-gradient-to-br from-pc-ring to-amber-600' }}">
                            @if($meeting->isLive())
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            @else
                                <svg class="w-5 h-5 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            @endif
                        </div>
                        <div>
                            <div class="text-sm font-bold text-crm-t1">{{ $meeting->title ?? 'Call' }}</div>
                            <div class="flex items-center gap-2 text-xs text-crm-t3 mt-0.5">
                                <span>{{ $meeting->host?->name ?? 'Unknown' }}</span>
                                <span class="w-1 h-1 rounded-full bg-crm-t3"></span>
                                @if($meeting->isLive())
                                    <span class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-pc-live pc-live-pulse"></span> Live</span>
                                @else
                                    <span class="text-pc-ring">{{ $meeting->isRinging() ? 'Ringing' : 'Waiting' }}</span>
                                @endif
                                <span class="w-1 h-1 rounded-full bg-crm-t3"></span>
                                <span>{{ $meeting->activeParticipants()->count() }} connected</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <a href="{{ route('meeting-room', ['uuid' => $meeting->uuid]) }}"
                            class="px-5 py-2 text-xs font-bold text-white bg-pc-live rounded-lg hover:brightness-110 transition flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            Join
                        </a>
                        @if($meeting->host_user_id === auth()->id() || $isAdmin)
                            <button wire:click="endMeeting({{ $meeting->id }})" wire:confirm="End this call for all participants?"
                                class="px-3 py-2 text-xs font-semibold text-pc-end bg-pc-end/10 rounded-lg hover:bg-pc-end/20 transition">End</button>
                        @endif
                    </div>
                </div>

                {{-- Participants --}}
                <div class="flex items-center gap-0.5 mt-3 pl-14">
                    @foreach($meeting->participants->take(8) as $p)
                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-[8px] font-bold text-white border-2 border-white -ml-1.5 first:ml-0 shadow-sm" style="background: {{ $p->user?->color ?? '#6b7280' }}" title="{{ $p->user?->name }}">
                            {{ $p->user?->avatar ?? substr($p->user?->name ?? '?', 0, 2) }}
                        </div>
                    @endforeach
                    @if($meeting->participants->count() > 8)
                        <span class="text-[10px] text-crm-t3 ml-1.5">+{{ $meeting->participants->count() - 8 }}</span>
                    @endif
                </div>
            </div>
        @empty
            <div class="bg-white border border-crm-border rounded-2xl p-16 text-center">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-gradient-to-br from-pc-primary/10 to-pc-accent/10 flex items-center justify-center">
                    <svg class="w-8 h-8 text-pc-primary/40" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div class="text-sm font-bold text-crm-t1 mb-1">No active sessions</div>
                <p class="text-xs text-crm-t3">Start a new call or wait for an invite</p>
            </div>
        @endforelse
    @endif

    {{-- History --}}
    @if($tab === 'past')
        @forelse($pastMeetings as $idx => $meeting)
            <div class="bg-white border border-crm-border rounded-xl px-4 py-3 mb-2 flex items-center justify-between hover:border-crm-border-h transition pc-card-enter" style="animation-delay: {{ $idx * 0.03 }}s">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-crm-surface flex items-center justify-center text-crm-t3">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <div class="text-sm font-semibold">{{ $meeting->title ?? 'Call' }}</div>
                        <div class="text-[10px] text-crm-t3">
                            {{ $meeting->host?->name }}
                            · {{ $meeting->started_at?->format('M j, g:i A') ?? 'Not started' }}
                            @if($meeting->ended_at && $meeting->started_at)
                                · {{ $meeting->started_at->diffForHumans($meeting->ended_at, true) }}
                            @endif
                        </div>
                    </div>
                </div>
                <span class="text-[9px] font-bold px-2.5 py-1 rounded-full {{ match($meeting->status) {
                    'ended' => 'bg-crm-surface text-crm-t3',
                    'missed' => 'bg-pc-ring/10 text-pc-ring',
                    'declined' => 'bg-pc-end/10 text-pc-end',
                    'failed' => 'bg-pc-end/10 text-pc-end',
                    default => 'bg-crm-surface text-crm-t3',
                } }}">{{ ucfirst($meeting->status) }}</span>
            </div>
        @empty
            <p class="text-xs text-crm-t3 text-center py-12">No call history yet</p>
        @endforelse
    @endif
</div>
