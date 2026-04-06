<div class="p-5" x-data="videoCallApp()" x-init="init()" wire:poll.3s>

    {{-- Incoming invite toast (also handled globally by IncomingCallAlert) --}}
    @if($pendingInvite && !$roomId)
        @php
            $invCaller = $pendingInvite->room?->creator;
            $invCallerName = $invCaller?->name ?? 'Admin';
            $invIsVideo = ($pendingInvite->room?->media_mode ?? 'video') !== 'audio';
        @endphp
        <div class="fixed top-4 right-4 z-50 bg-white border-2 {{ $invIsVideo ? 'border-blue-500' : 'border-emerald-500' }} rounded-2xl shadow-2xl p-4 w-80 animate-bounce" style="animation-duration: 2s;">
            <div class="flex items-center gap-3 mb-3">
                @if($invCaller?->avatar_path)
                    <img src="{{ asset('storage/' . $invCaller->avatar_path) }}" class="w-10 h-10 rounded-full object-cover">
                @else
                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-white text-sm font-bold" style="background: {{ $invCaller?->color ?? '#3b82f6' }}">{{ $invCaller?->avatar ?? substr($invCallerName, 0, 2) }}</div>
                @endif
                <div>
                    <div class="text-sm font-bold">Incoming {{ $invIsVideo ? 'Video' : 'Audio' }} Call</div>
                    <div class="text-xs text-crm-t3">{{ $invCallerName }}</div>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('video-call', ['room' => $pendingInvite->room?->uuid]) }}"
                   class="flex-1 py-2 text-center text-xs font-bold text-white {{ $invIsVideo ? 'bg-blue-500 hover:bg-blue-600' : 'bg-emerald-500 hover:bg-emerald-600' }} rounded-lg transition">Answer</a>
                <button wire:click="declineInvite" class="flex-1 py-2 text-xs font-bold text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition">Decline</button>
            </div>
        </div>
    @endif

    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-bold">Video Calls</h2>
            <p class="text-xs text-crm-t3 mt-1">Internal group video calls</p>
        </div>
        @if($canCreate)
            <button wire:click="openCreateModal" class="px-4 py-2 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition">
                Start Group Call
            </button>
        @endif
    </div>

    @if(session('video_error'))
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-2 text-sm text-red-700">{{ session('video_error') }}</div>
    @endif

    {{-- ═══════════════════════════════════════════════
         ACTIVE ROOM VIEW
         ═══════════════════════════════════════════════ --}}
    @if($roomId && $room && !$room->isEnded())
        <div class="bg-gray-900 rounded-xl overflow-hidden" style="min-height: 70vh;">
            {{-- Room header --}}
            <div class="flex items-center justify-between px-4 py-3 bg-gray-800">
                <div class="flex items-center gap-3">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span class="text-white text-sm font-semibold">{{ $room->name ?? 'Group Call' }}</span>
                    <span class="text-gray-400 text-xs">{{ $participants->where('joined', true)->where('left', false)->count() }} in call</span>
                </div>
                <div class="flex items-center gap-2">
                    @if(Gate::allows('end', $room))
                        <button @click="$wire.endRoom().then(() => cleanup())" class="px-3 py-1.5 text-xs font-bold text-white bg-red-600 rounded-lg hover:bg-red-700 transition">End Call</button>
                    @endif
                </div>
            </div>

            {{-- Media permission error banner with retry --}}
            <div x-show="!!mediaError" x-cloak class="mx-4 mt-2 px-4 py-3 bg-amber-900/90 rounded-xl">
                <div class="flex items-start gap-3">
                    <span class="text-amber-300 text-lg mt-0.5">⚠️</span>
                    <div class="flex-1">
                        <div class="text-amber-100 text-xs font-semibold mb-1">Device Permission Required</div>
                        <div class="text-amber-200 text-xs leading-relaxed" x-text="mediaError"></div>
                        <div class="flex items-center gap-2 mt-2">
                            <button @click="retryMedia()" class="px-3 py-1.5 text-[10px] font-bold text-white bg-blue-500 rounded-lg hover:bg-blue-600 transition">
                                Retry Camera & Mic
                            </button>
                            <span class="text-[9px] text-amber-400">You can still hear and see others without local media</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Video grid --}}
            <div class="p-4">
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3" id="video-grid">
                    {{-- Local video --}}
                    <div class="relative bg-gray-800 rounded-lg overflow-hidden aspect-video">
                        <video id="local-video" autoplay muted playsinline class="w-full h-full object-cover" x-show="cameraOn"></video>
                        <div x-show="!cameraOn" class="absolute inset-0 flex items-center justify-center bg-gray-700">
                            <div class="w-16 h-16 rounded-full bg-blue-600 flex items-center justify-center text-white text-2xl font-bold">
                                {{ substr(auth()->user()->name, 0, 2) }}
                            </div>
                        </div>
                        <div class="absolute bottom-2 left-2 px-2 py-0.5 bg-black/60 rounded text-white text-[10px] font-semibold">You</div>
                        <div class="absolute top-2 right-2 flex gap-1">
                            <span x-show="!micOn" class="px-1.5 py-0.5 bg-red-500/80 rounded text-white text-[8px]">Muted</span>
                        </div>
                    </div>

                    {{-- Remote participants --}}
                    @foreach($participants->where('id', '!=', auth()->id()) as $p)
                        @if($p['joined'] && !$p['left'])
                            <div class="relative bg-gray-800 rounded-lg overflow-hidden aspect-video" id="participant-{{ $p['id'] }}">
                                <video id="remote-video-{{ $p['id'] }}" autoplay playsinline class="w-full h-full object-cover"></video>
                                <div class="absolute inset-0 flex items-center justify-center bg-gray-700" id="avatar-fallback-{{ $p['id'] }}">
                                    @if($p['avatar_path'])
                                        <img src="{{ asset('storage/' . $p['avatar_path']) }}" class="w-16 h-16 rounded-full object-cover">
                                    @elseif($p['avatar_emoji'])
                                        <div class="text-4xl">{{ $p['avatar_emoji'] }}</div>
                                    @else
                                        <div class="w-16 h-16 rounded-full flex items-center justify-center text-white text-2xl font-bold" style="background: {{ $p['color'] }}">
                                            {{ $p['avatar'] ?: substr($p['name'], 0, 2) }}
                                        </div>
                                    @endif
                                </div>
                                <div class="absolute bottom-2 left-2 px-2 py-0.5 bg-black/60 rounded text-white text-[10px] font-semibold">{{ $p['name'] }}</div>
                            </div>
                        @endif
                    @endforeach
                </div>

                {{-- Waiting participants --}}
                @php $waiting = $participants->where('status', 'pending'); @endphp
                @if($waiting->isNotEmpty())
                    <div class="mt-4 px-2">
                        <div class="text-gray-400 text-[10px] uppercase tracking-wider mb-2">Waiting to join ({{ $waiting->count() }})</div>
                        <div class="flex flex-wrap gap-2">
                            @foreach($waiting as $w)
                                <span class="px-2 py-1 bg-gray-800 rounded text-gray-300 text-xs">{{ $w['name'] }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Controls bar --}}
            <div class="flex items-center justify-center gap-4 py-4 bg-gray-800">
                <button @click="toggleMic()" :class="micOn ? 'bg-gray-600 hover:bg-gray-500' : 'bg-red-600 hover:bg-red-700'"
                    class="w-12 h-12 rounded-full flex items-center justify-center text-white text-lg transition">
                    <span x-text="micOn ? '🎙️' : '🔇'"></span>
                </button>
                <button @click="toggleCamera()" :class="cameraOn ? 'bg-gray-600 hover:bg-gray-500' : 'bg-red-600 hover:bg-red-700'"
                    class="w-12 h-12 rounded-full flex items-center justify-center text-white text-lg transition">
                    <span x-text="cameraOn ? '📹' : '📷'"></span>
                </button>
                <button @click="$wire.leaveRoom(); cleanup(); window.location.href='/video-call';"
                    class="w-14 h-14 rounded-full bg-red-600 hover:bg-red-700 flex items-center justify-center text-white transition shadow-lg"
                    title="Leave Call">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M5 3a2 2 0 00-2 2v1c0 8.284 6.716 15 15 15h1a2 2 0 002-2v-3.28a1 1 0 00-.684-.948l-4.493-1.498a1 1 0 00-1.21.502l-1.13 2.257a11.042 11.042 0 01-5.516-5.517l2.257-1.13a1 1 0 00.502-1.21L9.228 3.683A1 1 0 008.279 3H5z"/></svg>
                </button>
            </div>
        </div>

    {{-- ═══════════════════════════════════════════════
         CALL ENDED STATE
         ═══════════════════════════════════════════════ --}}
    @elseif($roomStatus === 'ended')
        <div class="bg-gray-900 rounded-xl p-12 text-center" style="min-height: 40vh;" x-init="cleanup()">
            <div class="text-5xl mb-4">📴</div>
            <div class="text-white text-xl font-bold mb-2">Call Ended</div>
            <p class="text-gray-400 text-sm mb-6">This call has been ended.</p>
            <a href="{{ route('video-call') }}" class="px-6 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">Back to Video Calls</a>
        </div>

    {{-- ═══════════════════════════════════════════════
         NO ACTIVE ROOM — SHOW ACTIVE ROOMS LIST
         ═══════════════════════════════════════════════ --}}
    @else
        @if($activeRooms->isNotEmpty())
            <div class="bg-crm-card border border-crm-border rounded-lg mb-4">
                <div class="px-4 py-3 border-b border-crm-border">
                    <div class="text-sm font-bold">Active Calls</div>
                </div>
                @foreach($activeRooms as $ar)
                    <div class="flex items-center justify-between px-4 py-3 border-b border-crm-border last:border-0">
                        <div>
                            <div class="text-sm font-semibold">{{ $ar->name ?? 'Group Call' }}</div>
                            <div class="text-[10px] text-crm-t3">Created by {{ $ar->creator?->name ?? '?' }} &middot; {{ $ar->created_at->diffForHumans() }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-[9px] font-bold px-2 py-0.5 rounded {{ $ar->status === 'active' ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600' }}">{{ ucfirst($ar->status) }}</span>
                            @if($ar->canBeJoinedBy(auth()->user()))
                                <a href="{{ route('video-call', ['room' => $ar->uuid]) }}" class="px-3 py-1.5 text-xs font-semibold text-white bg-emerald-500 rounded-lg hover:bg-emerald-600 transition">Join</a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="bg-crm-card border border-crm-border rounded-lg p-8 text-center">
            <div class="text-3xl mb-3">📹</div>
            <p class="text-sm text-crm-t3 mb-1">No active video call</p>
            @if($canCreate)
                <p class="text-xs text-crm-t3">Click "Start Group Call" to begin</p>
            @else
                <p class="text-xs text-crm-t3">You will be notified when you are invited to a call</p>
            @endif
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════
         CREATE GROUP CALL MODAL
         ═══════════════════════════════════════════════ --}}
    @if($showCreateModal)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center backdrop-blur-sm" wire:click.self="closeCreateModal">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl mx-4 flex flex-col" style="max-height: 85vh;">
                {{-- Header --}}
                <div class="flex items-center justify-between p-5 border-b border-crm-border flex-shrink-0">
                    <div>
                        <h3 class="text-base font-bold">Start Group Video Call</h3>
                        <p class="text-xs text-crm-t3 mt-0.5">Select agents to invite to this call</p>
                    </div>
                    <button wire:click="closeCreateModal" class="text-crm-t3 hover:text-crm-t1 text-lg">&times;</button>
                </div>

                {{-- Body --}}
                <div class="flex-1 overflow-hidden flex flex-col" style="min-height: 0;">
                    <div class="p-4 border-b border-crm-border flex-shrink-0">
                        {{-- Call name --}}
                        <input id="fld-vc-name" wire:model="callName" type="text" placeholder="Call name (optional)" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg mb-3 focus:outline-none focus:border-blue-400">

                        {{-- Search + actions --}}
                        <div class="flex items-center gap-2">
                            <input id="fld-vc-search" wire:model.live.debounce.300ms="agentSearch" type="text" placeholder="Search agents by name, email, role..." class="flex-1 px-3 py-2 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                            <button wire:click="addAllAgents" class="px-3 py-2 text-xs font-semibold text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition whitespace-nowrap">Add All Agents</button>
                            <button wire:click="clearAll" class="px-3 py-2 text-xs font-semibold text-crm-t3 bg-gray-100 rounded-lg hover:bg-gray-200 transition whitespace-nowrap">Clear All</button>
                        </div>
                    </div>

                    {{-- Selected agents chips --}}
                    @if(count($selectedAgentIds) > 0)
                        <div class="px-4 py-2 border-b border-crm-border flex-shrink-0 bg-blue-50/50">
                            <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Selected ({{ count($selectedAgentIds) }})</div>
                            <div class="flex flex-wrap gap-1.5 max-h-20 overflow-y-auto">
                                @foreach($selectedAgents as $sa)
                                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 text-blue-700 rounded-full text-[10px] font-semibold">
                                        {{ $sa->name }}
                                        <button wire:click="removeAgent({{ $sa->id }})" class="text-blue-400 hover:text-blue-600">&times;</button>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Agent list --}}
                    <div class="flex-1 overflow-y-auto p-2" style="min-height: 0;">
                        @forelse($availableAgents as $agent)
                            @php $isSelected = in_array($agent->id, $selectedAgentIds); @endphp
                            <div wire:click="toggleAgent({{ $agent->id }})"
                                 class="flex items-center gap-3 px-3 py-2.5 rounded-lg cursor-pointer transition {{ $isSelected ? 'bg-blue-50 border border-blue-200' : 'hover:bg-crm-hover' }}">
                                {{-- Checkbox --}}
                                <div class="w-5 h-5 rounded border-2 flex items-center justify-center flex-shrink-0 {{ $isSelected ? 'bg-blue-600 border-blue-600' : 'border-gray-300' }}">
                                    @if($isSelected)
                                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                    @endif
                                </div>

                                {{-- Avatar --}}
                                @if($agent->avatar_path)
                                    <img src="{{ asset('storage/' . $agent->avatar_path) }}" class="w-9 h-9 rounded-full object-cover flex-shrink-0">
                                @elseif($agent->avatar_emoji)
                                    <div class="w-9 h-9 rounded-full bg-gray-100 flex items-center justify-center text-lg flex-shrink-0">{{ $agent->avatar_emoji }}</div>
                                @else
                                    <div class="w-9 h-9 rounded-full flex items-center justify-center text-[10px] font-bold text-white flex-shrink-0" style="background: {{ $agent->color ?? '#6b7280' }}">{{ $agent->avatar ?? substr($agent->name, 0, 2) }}</div>
                                @endif

                                {{-- Info --}}
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-semibold truncate">{{ $agent->name }}</div>
                                    <div class="text-[10px] text-crm-t3">{{ ucfirst(str_replace('_', ' ', $agent->role)) }} &middot; {{ $agent->email }}</div>
                                </div>

                                {{-- Status badge --}}
                                @include('livewire.partials.presence-badge', ['user' => $agent])
                            </div>
                        @empty
                            <div class="py-8 text-center text-sm text-crm-t3">No agents found</div>
                        @endforelse
                    </div>
                </div>

                {{-- Footer --}}
                <div class="flex items-center justify-between p-4 border-t border-crm-border flex-shrink-0">
                    <span class="text-xs text-crm-t3">{{ count($selectedAgentIds) }} agent{{ count($selectedAgentIds) !== 1 ? 's' : '' }} selected</span>
                    <div class="flex gap-2">
                        <button wire:click="closeCreateModal" class="px-4 py-2 text-sm font-semibold text-crm-t2 bg-gray-100 rounded-lg hover:bg-gray-200 transition">Cancel</button>
                        <button wire:click="createGroupCall" wire:loading.attr="disabled"
                            class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition disabled:opacity-50"
                            {{ empty($selectedAgentIds) ? 'disabled' : '' }}>
                            <span wire:loading.remove wire:target="createGroupCall">Create Group Call</span>
                            <span wire:loading wire:target="createGroupCall">Creating...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

@if($roomId && $room && !$room->isEnded())
<script>
function videoCallApp() {
    return {
        micOn: true,
        cameraOn: true,
        localStream: null,
        peers: {},
        roomId: @json($roomId),
        userId: @json(auth()->id()),
        pollInterval: null,
        iceServers: [{ urls: 'stun:stun.l.google.com:19302' }],

        mediaError: '',

        async init() {
            await this.fetchIceServers();
            await this.initMedia();

            // Auto-join: mark this user as joined in DB
            try { await this.$wire.joinRoom(); } catch(e) {}

            // Auto-initiate: send WebRTC offers to all other joined participants
            try {
                const otherIds = await this.$wire.getOtherJoinedParticipantIds();
                console.log('Other joined participants:', otherIds);
                for (const uid of otherIds) {
                    await this.initiateCallTo(uid);
                }
            } catch(e) { console.warn('Auto-initiate failed:', e.message); }

            this.pollInterval = setInterval(() => this.pollForSignals(), 2000);
        },

        async fetchIceServers() {
            try {
                const r = await fetch('/ice-servers', { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' } });
                if (r.ok) {
                    const data = await r.json();
                    if (data.iceServers && data.iceServers.length) {
                        this.iceServers = data.iceServers;
                        console.log('ICE servers loaded from Twilio:', this.iceServers.length, 'servers');
                    }
                }
            } catch (e) {
                console.warn('Failed to fetch ICE servers, using fallback STUN:', e.message);
            }
        },

        async initMedia() {
            this.mediaError = '';

            // Step 1: Try full video + audio
            try {
                this.localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
                this.attachLocalStream();
                return;
            } catch (e) {
                if (e.name === 'NotAllowedError') {
                    this.cameraOn = false;
                    this.micOn = false;
                    this.mediaError = 'Camera and microphone access was blocked. Click the lock/camera icon in your browser address bar, allow access, then click Retry below.';
                    return;
                }
            }

            // Step 2: Try audio-only (camera busy, missing, or denied)
            try {
                this.localStream = await navigator.mediaDevices.getUserMedia({ video: false, audio: true });
                this.cameraOn = false;
                this.mediaError = 'Camera unavailable — audio only. To enable camera: click the lock icon in your browser address bar and allow camera access, then click Retry.';
                this.attachLocalStream();
                return;
            } catch (e) {
                if (e.name === 'NotAllowedError') {
                    this.cameraOn = false;
                    this.micOn = false;
                    this.mediaError = 'Microphone access was blocked. Click the lock/camera icon in your browser address bar, allow microphone access, then click Retry below.';
                    return;
                }
            }

            // Step 3: Try video-only (no mic)
            try {
                this.localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
                this.micOn = false;
                this.mediaError = 'Microphone unavailable — video only. To enable mic: click the lock icon in your browser address bar and allow microphone access.';
                this.attachLocalStream();
                return;
            } catch (e) {}

            // Step 4: Nothing works
            this.cameraOn = false;
            this.micOn = false;
            this.mediaError = 'Camera and microphone are unavailable. This may be because access was blocked or no devices were found. Click the lock icon in your browser address bar to check permissions, then click Retry.';
        },

        async retryMedia() {
            this.mediaError = '';
            if (this.localStream) {
                this.localStream.getTracks().forEach(t => t.stop());
                this.localStream = null;
            }
            this.micOn = true;
            this.cameraOn = true;
            await this.initMedia();
        },

        attachLocalStream() {
            const localVideo = document.getElementById('local-video');
            if (localVideo && this.localStream) localVideo.srcObject = this.localStream;
        },

        toggleMic() {
            this.micOn = !this.micOn;
            if (this.localStream) {
                this.localStream.getAudioTracks().forEach(t => t.enabled = this.micOn);
            }
            this.$wire.toggleMic();
        },

        toggleCamera() {
            this.cameraOn = !this.cameraOn;
            if (this.localStream) {
                this.localStream.getVideoTracks().forEach(t => t.enabled = this.cameraOn);
            }
            this.$wire.toggleCamera();
        },

        async pollForSignals() {
            try {
                // Check if room was ended by someone else
                const status = this.$wire.roomStatus;
                if (status === 'ended' || status === 'none') {
                    this.cleanup();
                    return;
                }

                const signals = await this.$wire.pollSignals();
                for (const sig of signals) {
                    await this.handleSignal(sig);
                }
            } catch (e) {}
        },

        async handleSignal(sig) {
            try {
                const fromId = sig.from;
                if (sig.type === 'offer') {
                    const pc = this.getOrCreatePeer(fromId);
                    await pc.setRemoteDescription(JSON.parse(sig.payload));
                    const answer = await pc.createAnswer();
                    await pc.setLocalDescription(answer);
                    this.$wire.sendSignal(fromId, 'answer', JSON.stringify(answer));
                } else if (sig.type === 'answer') {
                    const pc = this.peers[fromId];
                    if (pc) await pc.setRemoteDescription(JSON.parse(sig.payload));
                } else if (sig.type === 'ice') {
                    const pc = this.peers[fromId];
                    if (pc) await pc.addIceCandidate(JSON.parse(sig.payload));
                }
            } catch (e) {
                console.warn('Signal handling error:', e.message);
            }
        },

        getOrCreatePeer(remoteUserId) {
            if (this.peers[remoteUserId]) return this.peers[remoteUserId];

            const pc = new RTCPeerConnection({
                iceServers: this.iceServers
            });

            pc.onicecandidate = (e) => {
                if (e.candidate) {
                    this.$wire.sendSignal(remoteUserId, 'ice', JSON.stringify(e.candidate));
                }
            };

            pc.ontrack = (e) => {
                const vid = document.getElementById('remote-video-' + remoteUserId);
                if (vid && e.streams[0]) {
                    vid.srcObject = e.streams[0];
                    const fallback = document.getElementById('avatar-fallback-' + remoteUserId);
                    if (fallback) fallback.style.display = 'none';
                }
            };

            if (this.localStream) {
                this.localStream.getTracks().forEach(t => pc.addTrack(t, this.localStream));
            }

            this.peers[remoteUserId] = pc;
            return pc;
        },

        async initiateCallTo(remoteUserId) {
            const pc = this.getOrCreatePeer(remoteUserId);
            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);
            this.$wire.sendSignal(remoteUserId, 'offer', JSON.stringify(offer));
        },

        cleanup() {
            if (this.pollInterval) clearInterval(this.pollInterval);
            if (this.localStream) this.localStream.getTracks().forEach(t => t.stop());
            Object.values(this.peers).forEach(pc => pc.close());
            this.peers = {};
        }
    }
}
</script>
@else
<script>
function videoCallApp() { return { micOn: true, cameraOn: true, mediaError: '', init() {}, toggleMic() {}, toggleCamera() {}, cleanup() {}, retryMedia() {} } }
</script>
@endif
