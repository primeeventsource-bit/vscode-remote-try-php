<div class="h-[calc(100vh-3rem)] flex flex-col bg-gray-900" x-data="meetingApp()" x-init="init()">
    @if($meetingStatus === 'not_found')
        <div class="flex-1 flex items-center justify-center">
            <div class="text-center"><div class="text-4xl mb-3">❌</div><div class="text-white text-lg font-bold">Meeting not found</div><a href="/meetings" class="mt-4 inline-block px-4 py-2 text-sm text-white bg-blue-600 rounded-lg">Back to Meetings</a></div>
        </div>
    @elseif($meetingStatus === 'ended' || $meetingStatus === 'declined')
        <div class="flex-1 flex items-center justify-center" x-init="cleanup()">
            <div class="text-center"><div class="text-4xl mb-3">📴</div><div class="text-white text-lg font-bold">Meeting Ended</div><a href="/meetings" class="mt-4 inline-block px-4 py-2 text-sm text-white bg-blue-600 rounded-lg">Back to Meetings</a></div>
        </div>
    @elseif($meeting)
        {{-- Header --}}
        <div class="flex items-center justify-between px-4 py-3 bg-gray-800 flex-shrink-0">
            <div class="flex items-center gap-3">
                <span class="w-2.5 h-2.5 rounded-full animate-pulse" :class="connected ? 'bg-emerald-400' : 'bg-amber-400'"></span>
                <span class="text-white text-sm font-semibold">{{ $meeting->title ?? 'Meeting' }}</span>
                <span class="text-gray-400 text-xs" x-text="participantCount + ' in call'"></span>
                <span class="text-gray-500 text-[10px]" x-show="!connected" x-cloak>Connecting...</span>
            </div>
            <div class="flex items-center gap-2">
                @if($isHost)
                    <button @click="endMeeting()" class="px-3 py-1.5 text-xs font-bold text-white bg-red-600 rounded-lg hover:bg-red-700">End Meeting</button>
                @endif
                <button @click="leaveMeeting()" class="px-3 py-1.5 text-xs font-bold text-gray-300 bg-gray-700 rounded-lg hover:bg-gray-600">Leave</button>
            </div>
        </div>

        {{-- Media error --}}
        <div x-show="!!mediaError" x-cloak class="mx-4 mt-2 px-4 py-3 bg-amber-900/90 rounded-xl flex-shrink-0">
            <div class="flex items-center gap-3">
                <span class="text-amber-300 text-lg">⚠️</span>
                <div class="flex-1">
                    <div class="text-amber-200 text-xs" x-text="mediaError"></div>
                    <button @click="retryMedia()" class="mt-1 px-3 py-1 text-[10px] font-bold text-white bg-blue-500 rounded-lg">Retry</button>
                </div>
            </div>
        </div>

        {{-- Video grid --}}
        <div class="flex-1 p-4 overflow-hidden">
            <div class="grid gap-3 h-full" :class="participantCount <= 1 ? 'grid-cols-1' : (participantCount <= 4 ? 'grid-cols-2' : 'grid-cols-3')" id="meeting-video-grid">
                {{-- Local preview --}}
                <div class="relative bg-gray-800 rounded-lg overflow-hidden">
                    <video id="local-video" autoplay muted playsinline class="w-full h-full object-cover"></video>
                    <div x-show="!cameraOn" class="absolute inset-0 flex items-center justify-center bg-gray-700">
                        <div class="w-20 h-20 rounded-full bg-blue-600 flex items-center justify-center text-white text-3xl font-bold">{{ substr(auth()->user()->name, 0, 2) }}</div>
                    </div>
                    <div class="absolute bottom-2 left-2 px-2 py-0.5 bg-black/60 rounded text-white text-[10px] font-semibold">You</div>
                    <span x-show="!micOn" class="absolute top-2 right-2 px-1.5 py-0.5 bg-red-500/80 rounded text-white text-[8px]">Muted</span>
                </div>
                {{-- Remote participants render here dynamically via JS --}}
            </div>
        </div>

        {{-- Controls --}}
        <div class="flex items-center justify-center gap-4 py-4 bg-gray-800 flex-shrink-0">
            <button @click="toggleMic()" :class="micOn ? 'bg-gray-600 hover:bg-gray-500' : 'bg-red-600 hover:bg-red-700'" class="w-12 h-12 rounded-full flex items-center justify-center text-white text-lg transition">
                <span x-text="micOn ? '🎙️' : '🔇'"></span>
            </button>
            <button @click="toggleCamera()" :class="cameraOn ? 'bg-gray-600 hover:bg-gray-500' : 'bg-red-600 hover:bg-red-700'" class="w-12 h-12 rounded-full flex items-center justify-center text-white text-lg transition">
                <span x-text="cameraOn ? '📹' : '📷'"></span>
            </button>
            <button @click="leaveMeeting()" class="w-14 h-14 rounded-full bg-red-600 hover:bg-red-700 flex items-center justify-center text-white transition shadow-lg" title="Leave">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M5 3a2 2 0 00-2 2v1c0 8.284 6.716 15 15 15h1a2 2 0 002-2v-3.28a1 1 0 00-.684-.948l-4.493-1.498a1 1 0 00-1.21.502l-1.13 2.257a11.042 11.042 0 01-5.516-5.517l2.257-1.13a1 1 0 00.502-1.21L9.228 3.683A1 1 0 008.279 3H5z"/></svg>
            </button>
        </div>
    @else
        <div class="flex-1 flex items-center justify-center">
            <div class="text-center"><div class="text-3xl mb-3 animate-spin">⏳</div><div class="text-gray-400 text-sm">Loading meeting...</div></div>
        </div>
    @endif
</div>

@if($meeting && !$meeting->isEnded())
<script src="https://sdk.twilio.com/js/video/releases/2.28.1/twilio-video.min.js"></script>
<script>
function meetingApp() {
    return {
        micOn: true,
        cameraOn: true,
        connected: false,
        room: null,
        localTracks: [],
        mediaError: '',
        participantCount: 1,
        meetingUuid: @json($uuid),

        async init() {
            await this.connectToRoom();
        },

        async connectToRoom() {
            try {
                // 1. Get token from server
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const res = await fetch('/meetings/' + this.meetingUuid + '/token', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                });
                if (!res.ok) { this.mediaError = 'Failed to get meeting token (' + res.status + ')'; return; }
                const data = await res.json();

                // 2. Get local media
                let localTracks = [];
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
                    localTracks = stream.getTracks().map(t => new Twilio.Video.LocalVideoTrack(t).kind === 'audio'
                        ? new Twilio.Video.LocalAudioTrack(t) : new Twilio.Video.LocalVideoTrack(t));
                } catch(e) {
                    try {
                        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                        localTracks = [new Twilio.Video.LocalAudioTrack(stream.getAudioTracks()[0])];
                        this.cameraOn = false;
                        this.mediaError = 'Camera unavailable. Audio only.';
                    } catch(e2) {
                        this.mediaError = 'Camera and mic blocked. Click lock icon in browser to allow.';
                        this.micOn = false; this.cameraOn = false;
                    }
                }

                // Attach local preview
                const localVideo = document.getElementById('local-video');
                for (const t of localTracks) {
                    if (t.kind === 'video' && localVideo) { t.attach(localVideo); }
                }
                this.localTracks = localTracks;

                // 3. Connect to Twilio Video Room
                this.room = await Twilio.Video.connect(data.token, {
                    name: data.room,
                    tracks: localTracks,
                    dominantSpeaker: true,
                });

                this.connected = true;
                this.participantCount = this.room.participants.size + 1;
                console.log('Connected to room:', data.room, 'Participants:', this.room.participants.size);

                // 4. Handle existing participants
                this.room.participants.forEach(p => this.handleParticipant(p));

                // 5. Handle new participants
                this.room.on('participantConnected', p => {
                    console.log('Participant connected:', p.identity);
                    this.handleParticipant(p);
                    this.participantCount = this.room.participants.size + 1;
                });

                this.room.on('participantDisconnected', p => {
                    console.log('Participant disconnected:', p.identity);
                    const el = document.getElementById('remote-' + p.identity);
                    if (el) el.remove();
                    this.participantCount = this.room.participants.size + 1;
                });

                this.room.on('disconnected', () => {
                    this.connected = false;
                    this.cleanup();
                });

            } catch(e) {
                console.error('Meeting connect error:', e);
                this.mediaError = 'Connection failed: ' + e.message;
            }
        },

        handleParticipant(participant) {
            const grid = document.getElementById('meeting-video-grid');
            if (!grid) return;

            // Create container for this participant
            let container = document.getElementById('remote-' + participant.identity);
            if (!container) {
                container = document.createElement('div');
                container.id = 'remote-' + participant.identity;
                container.className = 'relative bg-gray-800 rounded-lg overflow-hidden';

                // Name label
                const label = document.createElement('div');
                label.className = 'absolute bottom-2 left-2 px-2 py-0.5 bg-black/60 rounded text-white text-[10px] font-semibold';
                label.textContent = participant.identity.replace('user-', 'User ');
                container.appendChild(label);

                grid.appendChild(container);
            }

            // Handle existing tracks
            participant.tracks.forEach(pub => {
                if (pub.isSubscribed && pub.track) {
                    this.attachTrack(pub.track, container);
                }
            });

            // Handle new track subscriptions
            participant.on('trackSubscribed', track => {
                this.attachTrack(track, container);
            });

            participant.on('trackUnsubscribed', track => {
                track.detach().forEach(el => el.remove());
            });
        },

        attachTrack(track, container) {
            if (track.kind === 'video') {
                const existing = container.querySelector('video');
                if (existing) existing.remove();
                const el = track.attach();
                el.className = 'w-full h-full object-cover';
                el.style.position = 'absolute';
                el.style.top = '0';
                el.style.left = '0';
                container.style.position = 'relative';
                container.insertBefore(el, container.firstChild);
            } else if (track.kind === 'audio') {
                const el = track.attach();
                document.body.appendChild(el); // Audio elements go in body
            }
        },

        toggleMic() {
            this.micOn = !this.micOn;
            this.localTracks.forEach(t => { if (t.kind === 'audio') t.enable(this.micOn); });
        },

        toggleCamera() {
            this.cameraOn = !this.cameraOn;
            this.localTracks.forEach(t => { if (t.kind === 'video') t.enable(this.cameraOn); });
        },

        async retryMedia() {
            this.mediaError = '';
            if (this.room) {
                this.room.disconnect();
                this.room = null;
            }
            this.cleanup();
            this.micOn = true;
            this.cameraOn = true;
            await this.connectToRoom();
        },

        async leaveMeeting() {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            await fetch('/meetings/' + this.meetingUuid + '/leave', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf },
            }).catch(() => {});
            this.cleanup();
            window.location.href = '/meetings';
        },

        async endMeeting() {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            await fetch('/meetings/' + this.meetingUuid + '/end', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf },
            }).catch(() => {});
            this.cleanup();
            window.location.href = '/meetings';
        },

        cleanup() {
            if (this.room) { this.room.disconnect(); this.room = null; }
            this.localTracks.forEach(t => { t.stop(); t.detach().forEach(el => el.remove()); });
            this.localTracks = [];
            document.querySelectorAll('audio[autoplay]').forEach(el => el.remove());
        }
    }
}
</script>
@else
<script>function meetingApp() { return { micOn: true, cameraOn: true, connected: false, mediaError: '', participantCount: 0, meetingUuid: '', init() {}, toggleMic() {}, toggleCamera() {}, cleanup() {}, leaveMeeting() {}, endMeeting() {}, retryMedia() {} } }</script>
@endif
