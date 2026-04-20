<div class="h-[calc(100vh-3rem)] flex flex-col bg-pc-dark" wire:ignore x-data="meetingApp()" x-init="init()">
    <style>
        @keyframes pc-connect-pulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(37,99,235,0.4); } 50% { box-shadow: 0 0 0 12px rgba(37,99,235,0); } }
        @keyframes pc-ring-wave { 0% { transform: scale(1); opacity: 0.6; } 100% { transform: scale(2.5); opacity: 0; } }
        @keyframes pc-waveform { 0%, 100% { transform: scaleY(0.3); } 50% { transform: scaleY(1); } }
        .pc-connecting { animation: pc-connect-pulse 2s ease-in-out infinite; }
    </style>

    {{-- ═══ NOT FOUND STATE ═══ --}}
    @if($meetingStatus === 'not_found')
        <div class="flex-1 flex items-center justify-center">
            <div class="text-center max-w-sm">
                <div class="w-20 h-20 mx-auto mb-5 rounded-2xl bg-pc-surface flex items-center justify-center">
                    <svg class="w-10 h-10 text-pc-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                </div>
                <div class="text-white text-lg font-bold mb-1">Session Not Found</div>
                <p class="text-pc-muted text-sm mb-6">This Prime Connect session doesn't exist or has expired.</p>
                <a href="/calls" class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-bold text-white bg-gradient-to-r from-pc-primary to-pc-accent rounded-xl hover:shadow-lg hover:shadow-pc-primary/30 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back to Prime Connect
                </a>
            </div>
        </div>

    {{-- ═══ ENDED / DECLINED STATE ═══ --}}
    @elseif($meetingStatus === 'ended' || $meetingStatus === 'declined')
        <div class="flex-1 flex items-center justify-center" x-init="cleanup()">
            <div class="text-center max-w-sm">
                <div class="w-20 h-20 mx-auto mb-5 rounded-2xl bg-pc-surface flex items-center justify-center">
                    <svg class="w-10 h-10 text-pc-end" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 8l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M5 3a2 2 0 00-2 2v1c0 8.284 6.716 15 15 15h1a2 2 0 002-2v-3.28a1 1 0 00-.684-.948l-4.493-1.498a1 1 0 00-1.21.502l-1.13 2.257a11.042 11.042 0 01-5.516-5.517l2.257-1.13a1 1 0 00.502-1.21L9.228 3.683A1 1 0 008.279 3H5z"/>
                    </svg>
                </div>
                <div class="text-white text-lg font-bold mb-1">Session Ended</div>
                <p class="text-pc-muted text-sm mb-6">This Prime Connect session has ended.</p>
                <a href="/calls" class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-bold text-white bg-gradient-to-r from-pc-primary to-pc-accent rounded-xl hover:shadow-lg hover:shadow-pc-primary/30 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back to Prime Connect
                </a>
            </div>
        </div>

    {{-- ═══ ACTIVE MEETING ═══ --}}
    @elseif($meeting)
        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-3 bg-pc-surface/80 backdrop-blur-sm flex-shrink-0 border-b border-white/5">
            <div class="flex items-center gap-3">
                {{-- Logo --}}
                <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-pc-primary to-pc-accent flex items-center justify-center shadow-sm">
                    <span class="text-sm">🔗</span>
                </div>
                {{-- Status --}}
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-white text-sm font-bold">{{ $meeting->title ?? 'Prime Connect' }}</span>
                        <span class="flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold" :class="connected ? 'bg-pc-live/20 text-pc-live' : 'bg-pc-ring/20 text-pc-ring'">
                            <span class="w-1.5 h-1.5 rounded-full" :class="connected ? 'bg-pc-live animate-pulse' : 'bg-pc-ring animate-pulse'"></span>
                            <span x-text="connected ? 'Live' : 'Connecting...'"></span>
                        </span>
                    </div>
                    <div class="text-pc-muted text-[11px] mt-0.5">
                        <span x-text="participantCount"></span> participant<span x-show="participantCount !== 1">s</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if($isHost)
                    <button @click="endMeeting()" class="px-4 py-2 text-xs font-bold text-white bg-pc-end rounded-lg hover:brightness-110 transition flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        End for All
                    </button>
                @endif
                <button @click="leaveMeeting()" class="px-4 py-2 text-xs font-bold text-pc-muted bg-pc-panel rounded-lg hover:bg-white/10 transition">Leave</button>
            </div>
        </div>

        {{-- Media Error --}}
        <div x-show="!!mediaError" x-cloak x-transition class="mx-4 mt-3 px-4 py-3 bg-pc-ring/10 border border-pc-ring/30 rounded-xl flex-shrink-0">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-pc-ring flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                <div class="flex-1">
                    <div class="text-pc-ring text-xs font-medium" x-text="mediaError"></div>
                    <button @click="retryMedia()" class="mt-1.5 px-3 py-1 text-[10px] font-bold text-white bg-pc-primary rounded-lg hover:brightness-110 transition">Retry Camera & Mic</button>
                </div>
            </div>
        </div>

        {{-- Video Grid --}}
        <div class="flex-1 p-4 overflow-hidden">
            <div class="grid gap-3 h-full" :class="participantCount <= 1 ? 'grid-cols-1 max-w-2xl mx-auto' : (participantCount <= 4 ? 'grid-cols-2' : 'grid-cols-2 lg:grid-cols-3')" id="meeting-video-grid">
                {{-- Local Preview --}}
                <div class="relative bg-pc-surface rounded-2xl overflow-hidden shadow-lg ring-1 ring-white/10">
                    <video id="local-video" autoplay muted playsinline class="w-full h-full object-cover"></video>
                    <div x-show="!cameraOn" class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-pc-surface to-pc-dark">
                        <div class="w-20 h-20 rounded-full bg-gradient-to-br from-pc-primary to-pc-accent flex items-center justify-center text-white text-3xl font-bold shadow-lg">{{ substr(auth()->user()->name, 0, 2) }}</div>
                    </div>
                    <div class="absolute bottom-3 left-3 flex items-center gap-1.5 px-2.5 py-1 bg-black/50 backdrop-blur-sm rounded-lg">
                        <span class="text-white text-[11px] font-semibold">You</span>
                    </div>
                    <span x-show="!micOn" x-cloak class="absolute top-3 right-3 w-7 h-7 flex items-center justify-center bg-pc-end/90 rounded-full shadow-sm">
                        <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/><path stroke-linecap="round" stroke-linejoin="round" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"/></svg>
                    </span>
                </div>
                {{-- Remote participants render here dynamically via JS --}}
            </div>
        </div>

        {{-- Control Dock --}}
        <div class="flex items-center justify-center gap-3 py-5 bg-pc-surface/60 backdrop-blur-sm flex-shrink-0 border-t border-white/5">
            {{-- View-Only Badge --}}
            <template x-if="viewOnly">
                <span class="px-2 py-1 text-[10px] font-bold text-white bg-pc-ring/80 rounded-md self-center" title="No camera or mic detected">VIEW ONLY</span>
            </template>

            {{-- Mic Toggle --}}
            <button @click="toggleMic()" :disabled="viewOnly" class="group relative w-14 h-14 rounded-full flex items-center justify-center text-white transition-all duration-200 disabled:opacity-40 disabled:cursor-not-allowed"
                :class="micOn ? 'bg-pc-panel hover:bg-white/15' : 'bg-pc-end hover:brightness-110'">
                <template x-if="micOn">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
                </template>
                <template x-if="!micOn">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/><path stroke-linecap="round" stroke-linejoin="round" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"/></svg>
                </template>
                <span class="absolute -bottom-5 text-[9px] font-semibold" :class="micOn ? 'text-pc-muted' : 'text-pc-end'" x-text="micOn ? 'Mic On' : 'Muted'"></span>
            </button>

            {{-- Camera Toggle --}}
            <button @click="toggleCamera()" :disabled="viewOnly" class="group relative w-14 h-14 rounded-full flex items-center justify-center text-white transition-all duration-200 disabled:opacity-40 disabled:cursor-not-allowed"
                :class="cameraOn ? 'bg-pc-panel hover:bg-white/15' : 'bg-pc-end hover:brightness-110'">
                <template x-if="cameraOn">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                </template>
                <template x-if="!cameraOn">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18"/></svg>
                </template>
                <span class="absolute -bottom-5 text-[9px] font-semibold" :class="cameraOn ? 'text-pc-muted' : 'text-pc-end'" x-text="cameraOn ? 'Camera On' : 'Camera Off'"></span>
            </button>

            {{-- End Call --}}
            <button @click="leaveMeeting()" class="group relative w-16 h-16 rounded-full bg-pc-end hover:brightness-110 flex items-center justify-center text-white transition-all duration-200 shadow-lg shadow-pc-end/30 hover:shadow-pc-end/50 hover:scale-105">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 8l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M5 3a2 2 0 00-2 2v1c0 8.284 6.716 15 15 15h1a2 2 0 002-2v-3.28a1 1 0 00-.684-.948l-4.493-1.498a1 1 0 00-1.21.502l-1.13 2.257a11.042 11.042 0 01-5.516-5.517l2.257-1.13a1 1 0 00.502-1.21L9.228 3.683A1 1 0 008.279 3H5z"/></svg>
                <span class="absolute -bottom-5 text-[9px] font-semibold text-pc-end">End</span>
            </button>
        </div>

    {{-- ═══ LOADING STATE ═══ --}}
    @else
        <div class="flex-1 flex items-center justify-center">
            <div class="text-center">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-gradient-to-br from-pc-primary to-pc-accent flex items-center justify-center pc-connecting">
                    <span class="text-3xl">🔗</span>
                </div>
                <div class="text-white text-sm font-semibold">Connecting to Prime Connect...</div>
                <div class="flex items-center justify-center gap-[3px] h-4 mt-3">
                    @for($i = 0; $i < 5; $i++)
                        <div class="w-[3px] bg-pc-primary rounded-full" style="animation: pc-waveform 1s ease-in-out {{ $i * 0.12 }}s infinite; height: 100%;"></div>
                    @endfor
                </div>
            </div>
        </div>
    @endif
</div>

@if($meeting && !$meeting->isEnded())
<script src="https://sdk.twilio.com/js/video/releases/2.28.1/twilio-video.min.js"></script>
<script>
let _twilioRoom = null;
let _twilioLocalTracks = [];
let _twilioConnecting = false;

function meetingApp() {
    return {
        micOn: true,
        cameraOn: true,
        connected: false,
        viewOnly: false,
        mediaError: '',
        participantCount: 1,
        meetingUuid: @json($uuid),

        async init() {
            if (_twilioRoom || _twilioConnecting) {
                if (_twilioRoom) this.connected = true;
                return;
            }
            await this.connectToRoom();
        },

        async connectToRoom() {
            if (_twilioRoom || _twilioConnecting) return;
            _twilioConnecting = true;

            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const res = await fetch('/meetings/' + this.meetingUuid + '/token', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                });
                if (!res.ok) {
                    let errMsg = 'Failed to get session token (' + res.status + ')';
                    try { const body = await res.json(); errMsg += ': ' + (body.error || ''); } catch(e) {}
                    this.mediaError = errMsg;
                    _twilioConnecting = false;
                    return;
                }
                const data = await res.json();

                // Pre-flight: getUserMedia requires a secure context AND mediaDevices API.
                if (!window.isSecureContext) {
                    this.mediaError = 'Camera/mic require HTTPS. Reload the page using https://...';
                    this.micOn = false; this.cameraOn = false;
                    _twilioConnecting = false; return;
                }
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    this.mediaError = 'This browser does not support camera/mic. Use Chrome, Edge, Firefox, or Safari.';
                    this.micOn = false; this.cameraOn = false;
                    _twilioConnecting = false; return;
                }

                // Map browser error to a specific, actionable message
                const explainMediaError = (e, kind) => {
                    const name = e?.name || 'Error';
                    const detail = e?.message ? ` (${e.message})` : '';
                    switch (name) {
                        case 'NotAllowedError':
                        case 'SecurityError':
                            return `${kind} blocked: permission denied. Click the camera icon in the address bar -> Allow, then reload.`;
                        case 'NotFoundError':
                        case 'DevicesNotFoundError':
                            return `No ${kind.toLowerCase()} device found. Plug one in or pick the right one in browser settings.`;
                        case 'NotReadableError':
                        case 'TrackStartError':
                            return `${kind} is in use by another app (Zoom/Teams/OBS). Close it and retry.`;
                        case 'OverconstrainedError':
                        case 'ConstraintNotSatisfiedError':
                            return `${kind} cannot match the requested settings. Try a different device.`;
                        case 'AbortError':
                            return `${kind} request was dismissed. Click Try Again.`;
                        default:
                            return `${kind} error: ${name}${detail}`;
                    }
                };

                let localTracks = [];
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
                    for (const t of stream.getTracks()) {
                        if (t.kind === 'audio') localTracks.push(new Twilio.Video.LocalAudioTrack(t));
                        else localTracks.push(new Twilio.Video.LocalVideoTrack(t));
                    }
                } catch(e) {
                    console.warn('Camera+mic failed:', e);
                    try {
                        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                        localTracks = [new Twilio.Video.LocalAudioTrack(stream.getAudioTracks()[0])];
                        this.cameraOn = false;
                        this.mediaError = explainMediaError(e, 'Camera') + ' Audio-only mode active.';
                    } catch(e2) {
                        console.warn('Audio-only also failed:', e2);
                        // View-only fallback: join the room with no local tracks so the
                        // user can still see/hear other participants. Better than being
                        // locked out entirely when the device isn't available.
                        this.mediaError = explainMediaError(e2, 'Camera/mic') + ' Joined view-only — you can see and hear others.';
                        this.micOn = false; this.cameraOn = false;
                        this.viewOnly = true;
                    }
                }

                // Attach local preview
                const localVideo = document.getElementById('local-video');
                for (const t of localTracks) {
                    if (t.kind === 'video' && localVideo) t.attach(localVideo);
                }
                _twilioLocalTracks = localTracks;

                // Connect to Twilio
                _twilioRoom = await Twilio.Video.connect(data.token, {
                    name: data.room,
                    tracks: localTracks,
                    dominantSpeaker: true,
                    networkQuality: { local: 1, remote: 1 },
                });

                _twilioConnecting = false;
                this.connected = true;
                this.participantCount = _twilioRoom.participants.size + 1;

                _twilioRoom.participants.forEach(p => this.handleParticipant(p));

                _twilioRoom.on('participantConnected', p => {
                    this.handleParticipant(p);
                    if (_twilioRoom) this.participantCount = _twilioRoom.participants.size + 1;
                });

                _twilioRoom.on('participantDisconnected', p => {
                    const el = document.getElementById('remote-' + p.identity);
                    if (el) el.remove();
                    if (_twilioRoom) this.participantCount = _twilioRoom.participants.size + 1;
                });

                _twilioRoom.on('reconnecting', () => { this.mediaError = 'Reconnecting...'; });
                _twilioRoom.on('reconnected', () => { this.mediaError = ''; this.connected = true; });
                _twilioRoom.on('disconnected', (room, error) => {
                    console.warn('[Prime Connect] Disconnected:', error?.message || 'clean disconnect');
                    this.connected = false;
                    if (error) {
                        this.mediaError = 'Connection lost: ' + (error.message || 'Unknown error') + '. Try leaving and rejoining.';
                    }
                    _twilioRoom = null;
                    _twilioConnecting = false;
                });

            } catch(e) {
                this.mediaError = 'Connection failed: ' + e.message;
                _twilioConnecting = false;
            }
        },

        handleParticipant(participant) {
            const grid = document.getElementById('meeting-video-grid');
            if (!grid) return;

            let container = document.getElementById('remote-' + participant.identity);
            if (!container) {
                container = document.createElement('div');
                container.id = 'remote-' + participant.identity;
                container.className = 'relative bg-pc-surface rounded-2xl overflow-hidden shadow-lg ring-1 ring-white/10';
                const label = document.createElement('div');
                label.className = 'absolute bottom-3 left-3 flex items-center gap-1.5 px-2.5 py-1 bg-black/50 backdrop-blur-sm rounded-lg z-10';
                label.innerHTML = '<span class="text-white text-[11px] font-semibold">' + participant.identity.replace('user-', 'User ') + '</span>';
                container.appendChild(label);
                grid.appendChild(container);
            }

            participant.tracks.forEach(pub => {
                if (pub.isSubscribed && pub.track) this.attachTrack(pub.track, container);
            });
            participant.on('trackSubscribed', track => this.attachTrack(track, container));
            participant.on('trackUnsubscribed', track => { track.detach().forEach(el => el.remove()); });
        },

        attachTrack(track, container) {
            if (track.kind === 'video') {
                const existing = container.querySelector('video');
                if (existing) existing.remove();
                const el = track.attach();
                el.className = 'w-full h-full object-cover absolute top-0 left-0';
                container.insertBefore(el, container.firstChild);
            } else if (track.kind === 'audio') {
                document.body.appendChild(track.attach());
            }
        },

        toggleMic() {
            if (this.viewOnly) return; // no local audio track to toggle
            this.micOn = !this.micOn;
            _twilioLocalTracks.forEach(t => { if (t.kind === 'audio') t.enable(this.micOn); });
        },

        toggleCamera() {
            if (this.viewOnly) return; // no local video track to toggle
            this.cameraOn = !this.cameraOn;
            _twilioLocalTracks.forEach(t => { if (t.kind === 'video') t.enable(this.cameraOn); });
        },

        async retryMedia() {
            this.mediaError = '';
            this.cleanup();
            this.micOn = true;
            this.cameraOn = true;
            this.viewOnly = false;
            await this.connectToRoom();
        },

        async leaveMeeting() {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            fetch('/meetings/' + this.meetingUuid + '/leave', { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': csrf } }).catch(() => {});
            this.cleanup();
            window.location.href = '/calls';
        },

        async endMeeting() {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            fetch('/meetings/' + this.meetingUuid + '/end', { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': csrf } }).catch(() => {});
            this.cleanup();
            window.location.href = '/calls';
        },

        cleanup() {
            if (_twilioRoom) { try { _twilioRoom.disconnect(); } catch(e) {} _twilioRoom = null; }
            _twilioConnecting = false;
            _twilioLocalTracks.forEach(t => { try { t.stop(); } catch(e) {} try { t.detach().forEach(el => el.remove()); } catch(e) {} });
            _twilioLocalTracks = [];
            document.querySelectorAll('audio[autoplay]').forEach(el => el.remove());
        }
    }
}

window.addEventListener('beforeunload', () => {
    if (_twilioRoom) { try { _twilioRoom.disconnect(); } catch(e) {} _twilioRoom = null; }
});
</script>
@else
<script>function meetingApp() { return { micOn:true, cameraOn:true, connected:false, mediaError:'', participantCount:0, meetingUuid:'', init(){}, toggleMic(){}, toggleCamera(){}, cleanup(){}, leaveMeeting(){}, endMeeting(){}, retryMedia(){} } }</script>
@endif
