<div class="p-5" wire:poll.10s>
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-bold">Meetings</h2>
            <p class="text-xs text-crm-t3 mt-0.5">Team video meetings and group calls</p>
        </div>
        <button wire:click="$set('showCreate', true)" class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">+ New Meeting</button>
    </div>

    {{-- Pending Invites Banner --}}
    @if($pendingInvites->isNotEmpty())
        <div class="mb-4 space-y-2">
            @foreach($pendingInvites as $invite)
                <div class="flex items-center justify-between bg-blue-50 border border-blue-200 rounded-lg px-4 py-3">
                    <div class="flex items-center gap-3">
                        <span class="text-lg">📹</span>
                        <div>
                            <div class="text-sm font-bold">{{ $invite->room?->name ?? 'Meeting' }}</div>
                            <div class="text-xs text-crm-t3">Invited by {{ $invite->room?->creator?->name ?? 'Admin' }}</div>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <a href="{{ route('video-call', ['room' => $invite->room?->uuid]) }}" class="px-3 py-1.5 text-xs font-semibold text-white bg-emerald-500 rounded-lg hover:bg-emerald-600">Join</a>
                        <button wire:click="$set('tab', 'active')" class="px-3 py-1.5 text-xs font-semibold text-crm-t2 bg-gray-100 rounded-lg hover:bg-gray-200">Later</button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Tabs --}}
    <div class="flex gap-1 bg-crm-card border border-crm-border rounded-lg p-0.5 mb-5">
        @foreach(['active' => 'Active (' . $activeMeetings->count() . ')', 'past' => 'Past Meetings'] as $k => $l)
            <button wire:click="$set('tab', '{{ $k }}')" class="px-3 py-1.5 text-xs font-semibold rounded-md transition {{ $tab === $k ? 'bg-white text-blue-600 shadow-sm' : 'text-crm-t3 hover:text-crm-t1' }}">{{ $l }}</button>
        @endforeach
    </div>

    {{-- Create Meeting Modal --}}
    @if($showCreate)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center" wire:click.self="$set('showCreate', false)">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                <h3 class="text-lg font-bold mb-4">Create Meeting</h3>

                <div class="space-y-3">
                    <div>
                        <label for="fld-meeting-name" class="text-xs text-crm-t3 uppercase font-semibold">Meeting Name</label>
                        <input id="fld-meeting-name" wire:model="meetingName" type="text" placeholder="Daily Standup, Training Session..." class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg focus:border-blue-400 focus:outline-none">
                    </div>

                    <div>
                        <label for="fld-meeting-type" class="text-xs text-crm-t3 uppercase font-semibold">Type</label>
                        <select id="fld-meeting-type" wire:model="meetingType" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg">
                            <option value="instant">Instant Meeting</option>
                            <option value="department">Department Meeting</option>
                            <option value="coaching">Coaching Call</option>
                            <option value="training">Training Session</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs text-crm-t3 uppercase font-semibold">Invite Participants</label>
                        <div class="max-h-48 overflow-y-auto border border-crm-border rounded-lg mt-1">
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
                </div>

                <div class="flex gap-2 mt-4">
                    <button wire:click="$set('showCreate', false)" class="flex-1 px-4 py-2 text-sm font-semibold text-crm-t2 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
                    <button wire:click="createMeeting" class="flex-1 px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700">Start Meeting</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Active Meetings --}}
    @if($tab === 'active')
        @forelse($activeMeetings as $meeting)
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 mb-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl {{ $meeting->isActive() ? 'bg-emerald-500' : 'bg-amber-500' }} flex items-center justify-center text-white text-lg">
                            {{ $meeting->isActive() ? '🟢' : '⏳' }}
                        </div>
                        <div>
                            <div class="text-sm font-bold">{{ $meeting->name ?? $meeting->room_name }}</div>
                            <div class="text-xs text-crm-t3">
                                Hosted by {{ $meeting->creator?->name ?? 'Unknown' }}
                                · {{ $meeting->isActive() ? 'Live' : 'Waiting' }}
                                · {{ $meeting->activeParticipants()->count() }} in call
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <a href="{{ route('video-call', ['room' => $meeting->uuid]) }}" class="px-4 py-2 text-xs font-semibold text-white bg-emerald-500 rounded-lg hover:bg-emerald-600 transition">Join</a>
                        @if($meeting->created_by === auth()->id() || $isAdmin)
                            <button wire:click="endMeeting({{ $meeting->id }})" wire:confirm="End this meeting for all participants?" class="px-3 py-2 text-xs font-semibold text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition">End</button>
                        @endif
                    </div>
                </div>

                {{-- Participant Avatars --}}
                <div class="flex items-center gap-1 mt-3">
                    @foreach($meeting->participants->take(8) as $p)
                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-[8px] font-bold text-white border-2 border-white -ml-1 first:ml-0" style="background: {{ $p->user?->color ?? '#6b7280' }}" title="{{ $p->user?->name }}">
                            {{ $p->user?->avatar ?? substr($p->user?->name ?? '?', 0, 2) }}
                        </div>
                    @endforeach
                    @if($meeting->participants->count() > 8)
                        <span class="text-[10px] text-crm-t3 ml-1">+{{ $meeting->participants->count() - 8 }} more</span>
                    @endif
                </div>
            </div>
        @empty
            <div class="bg-crm-card border border-crm-border rounded-lg p-12 text-center">
                <div class="text-3xl mb-2">📹</div>
                <div class="text-sm font-bold mb-1">No active meetings</div>
                <p class="text-xs text-crm-t3">Start a new meeting or wait for an invite</p>
            </div>
        @endforelse
    @endif

    {{-- Past Meetings --}}
    @if($tab === 'past')
        @forelse($pastMeetings as $meeting)
            <div class="bg-crm-card border border-crm-border rounded-lg px-4 py-3 mb-2 flex items-center justify-between">
                <div>
                    <div class="text-sm font-semibold">{{ $meeting->name ?? $meeting->room_name }}</div>
                    <div class="text-[10px] text-crm-t3">
                        {{ $meeting->creator?->name }} · {{ $meeting->started_at?->format('M j, g:i A') ?? 'Not started' }}
                        @if($meeting->ended_at && $meeting->started_at)
                            · {{ $meeting->started_at->diffForHumans($meeting->ended_at, true) }} duration
                        @endif
                    </div>
                </div>
                <span class="text-[9px] font-bold px-2 py-0.5 rounded bg-gray-100 text-gray-500">Ended</span>
            </div>
        @empty
            <p class="text-xs text-crm-t3 text-center py-8">No past meetings</p>
        @endforelse
    @endif
</div>
