<div class="p-5">
    <div class="mb-5">
        <h2 class="text-xl font-bold">Training & Help Center</h2>
        <p class="text-xs text-crm-t3 mt-1">Learn the CRM, track your progress, and access resources</p>
    </div>

    {{-- Section Nav --}}
    <div class="flex flex-wrap items-center gap-1 bg-crm-card border border-crm-border rounded-lg p-0.5 mb-5">
        @php
            $sections = ['my_training' => 'My Training'];
            if ($isAdmin) $sections['team_progress'] = 'Team Progress';
            $sections['help'] = 'Help & Resources';
        @endphp
        @foreach($sections as $key => $label)
            <button wire:click="$set('section', '{{ $key }}')"
                class="px-3 py-1.5 text-xs font-semibold rounded-md transition {{ $section === $key ? 'bg-white text-blue-600 shadow-sm' : 'text-crm-t3 hover:text-crm-t1' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- ═══════════════════════════════════════════════
         MY TRAINING
    ═══════════════════════════════════════════════ --}}
    @if($section === 'my_training')
        @if($progress['total'] > 0)
            {{-- Progress bar --}}
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 mb-5">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <div class="text-sm font-bold">{{ $progress['flow']->name ?? 'Onboarding' }}</div>
                        <div class="text-[10px] text-crm-t3">{{ $progress['completed'] }} of {{ $progress['total'] }} steps completed</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-lg font-extrabold {{ $progress['is_complete'] ? 'text-emerald-500' : 'text-blue-500' }}">{{ $progress['pct'] }}%</span>
                        @if($progress['is_complete'])
                            <span class="text-[9px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-600">Complete</span>
                        @endif
                    </div>
                </div>
                <div class="h-2.5 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-500 {{ $progress['is_complete'] ? 'bg-emerald-500' : 'bg-blue-500' }}" style="width: {{ $progress['pct'] }}%"></div>
                </div>
            </div>

            {{-- Steps --}}
            <div class="space-y-2">
                @foreach($progress['steps'] as $step)
                    <div class="bg-crm-card border border-crm-border rounded-lg p-4 {{ $step['status'] === 'completed' ? 'bg-emerald-50/30' : ($step['status'] === 'skipped' ? 'bg-gray-50' : '') }}">
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-lg {{ $step['status'] === 'completed' ? 'bg-emerald-100' : ($step['status'] === 'skipped' ? 'bg-gray-100' : 'bg-blue-50') }}">
                                @if($step['status'] === 'completed')
                                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                @else
                                    {{ $step['icon'] }}
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold {{ $step['status'] === 'completed' ? 'text-emerald-700' : ($step['status'] === 'skipped' ? 'text-crm-t3 line-through' : '') }}">{{ $step['title'] }}</span>
                                    @if($step['status'] === 'skipped')
                                        <span class="text-[8px] font-bold px-1.5 py-0.5 rounded bg-gray-100 text-gray-500">Skipped</span>
                                    @endif
                                </div>
                                <p class="text-xs text-crm-t3 mt-0.5">{{ $step['description'] }}</p>
                                @if($step['target_route'])
                                    <a href="{{ $step['target_route'] }}" class="inline-block mt-1 text-[10px] text-blue-600 hover:underline">Open {{ str_replace('/', '', $step['target_route']) }} →</a>
                                @endif
                            </div>
                            <div class="flex items-center gap-1 flex-shrink-0">
                                @if($step['status'] === 'not_started')
                                    <button wire:click="completeStep({{ $step['id'] }})" class="px-2.5 py-1 text-[10px] font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">Done</button>
                                    <button wire:click="skipStep({{ $step['id'] }})" class="px-2 py-1 text-[10px] font-semibold text-crm-t3 bg-gray-100 rounded-lg hover:bg-gray-200 transition">Skip</button>
                                @elseif($step['status'] === 'skipped')
                                    <button wire:click="completeStep({{ $step['id'] }})" class="px-2.5 py-1 text-[10px] font-semibold text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition">Complete</button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Reset --}}
            <div class="mt-4 text-center">
                <button wire:click="resetMyOnboarding" wire:confirm="Reset all your training progress?" class="text-xs text-crm-t3 hover:text-red-500 transition">Reset my training progress</button>
            </div>
        @else
            <div class="bg-crm-card border border-crm-border rounded-lg p-8 text-center">
                <div class="text-3xl mb-3">📚</div>
                <p class="text-sm text-crm-t3">No training flow available for your role yet.</p>
            </div>
        @endif
    @endif

    {{-- ═══════════════════════════════════════════════
         TEAM PROGRESS (Admin/Master Admin)
    ═══════════════════════════════════════════════ --}}
    @if($section === 'team_progress' && $isAdmin)
        <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-crm-border bg-crm-surface">
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">User</th>
                        <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Role</th>
                        <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Progress</th>
                        <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Status</th>
                        @if($isMaster)
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($allUsersProgress as $up)
                        <tr class="border-b border-crm-border hover:bg-crm-hover transition">
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-[9px] font-bold text-white" style="background: {{ $up['color'] ?? '#6b7280' }}">{{ $up['avatar'] ?? '--' }}</div>
                                    <span class="font-semibold">{{ $up['name'] }}</span>
                                </div>
                            </td>
                            <td class="text-center px-3 py-2.5 text-xs text-crm-t3">{{ ucfirst(str_replace('_', ' ', $up['role'])) }}</td>
                            <td class="text-center px-3 py-2.5">
                                <div class="flex items-center justify-center gap-2">
                                    <div class="w-16 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full {{ $up['is_complete'] ? 'bg-emerald-500' : 'bg-blue-500' }}" style="width: {{ $up['pct'] }}%"></div>
                                    </div>
                                    <span class="text-[10px] font-mono font-bold">{{ $up['pct'] }}%</span>
                                </div>
                            </td>
                            <td class="text-center px-3 py-2.5">
                                <span class="text-[8px] font-bold px-1.5 py-0.5 rounded {{ $up['is_complete'] ? 'bg-emerald-50 text-emerald-600' : ($up['pct'] > 0 ? 'bg-amber-50 text-amber-600' : 'bg-gray-100 text-gray-500') }}">
                                    {{ $up['is_complete'] ? 'Complete' : ($up['pct'] > 0 ? 'In Progress' : 'Not Started') }}
                                </span>
                            </td>
                            @if($isMaster)
                                <td class="text-center px-3 py-2.5">
                                    <button wire:click="resetUserOnboarding({{ $up['user_id'] }})" wire:confirm="Reset training for {{ $up['name'] }}?" class="text-[10px] text-red-500 hover:text-red-600 font-semibold">Reset</button>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════
         HELP & RESOURCES
    ═══════════════════════════════════════════════ --}}
    @if($section === 'help')
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach([
                ['icon' => '📋', 'title' => 'Leads', 'desc' => 'Import, assign, and transfer leads to closers. Use dispositions to track status.', 'route' => '/leads'],
                ['icon' => '📊', 'title' => 'Deals & Pipeline', 'desc' => 'Convert leads to deals. Track through verification and charging.', 'route' => '/deals'],
                ['icon' => '💰', 'title' => 'Payroll & Commissions', 'desc' => 'Commission = Fee - SNR% - VD%. Closer gets their % of the payable amount.', 'route' => '/payroll'],
                ['icon' => '⚠️', 'title' => 'Chargebacks', 'desc' => 'Upload evidence, track required documents, and submit defense packages.', 'route' => '/clients'],
                ['icon' => '💬', 'title' => 'Chat', 'desc' => 'Direct messages, group chats, video/audio calls, GIFs, and file sharing.', 'route' => '/chat'],
                ['icon' => '📄', 'title' => 'Documents & Sheets', 'desc' => 'Create, edit, and share documents and spreadsheets with your team.', 'route' => '/documents'],
                ['icon' => '⚙️', 'title' => 'Settings', 'desc' => 'Configure CRM behavior, payroll rates, chat, uploads, and permissions.', 'route' => '/settings'],
                ['icon' => '👥', 'title' => 'User Management', 'desc' => 'Create accounts, assign roles, and manage team access.', 'route' => '/users'],
                ['icon' => '📈', 'title' => 'Statistics', 'desc' => 'Pipeline performance by fronter, closer, and admin with date filters.', 'route' => '/stats'],
                ['icon' => '☑️', 'title' => 'Automatic Task List', 'desc' => 'Tasks auto-created from workflow events. Require note before completion.', 'route' => '/tasks'],
                ['icon' => '📹', 'title' => 'Video & Audio Calls', 'desc' => 'Direct calls from DM threads. Group calls for admins.', 'route' => '/video-call'],
                ['icon' => '🟢', 'title' => 'Presence / Status', 'desc' => 'Green=Online, Yellow=Idle, Red=Offline. Updated automatically via heartbeat.', 'route' => '/settings'],
            ] as $card)
                <a href="{{ $card['route'] }}" class="bg-crm-card border border-crm-border rounded-lg p-4 hover:bg-crm-hover transition">
                    <div class="text-2xl mb-2">{{ $card['icon'] }}</div>
                    <div class="text-sm font-bold mb-1">{{ $card['title'] }}</div>
                    <p class="text-xs text-crm-t3">{{ $card['desc'] }}</p>
                </a>
            @endforeach
        </div>

        {{-- Payroll Formula Help --}}
        <div class="mt-6 bg-crm-card border border-crm-border rounded-lg p-5">
            <div class="text-sm font-bold mb-3">💵 Payroll Formula Explained</div>
            <div class="text-xs text-crm-t2 space-y-2">
                <div><span class="font-bold text-crm-t1">Step 1:</span> Payable = Deal Fee - SNR Deduction - VD Deduction (if applicable)</div>
                <div><span class="font-bold text-crm-t1">Step 2:</span> Closer Commission = Payable × Closer %</div>
                <div><span class="font-bold text-crm-t1">Step 3:</span> Fronter Commission = Payable × Fronter %</div>
                <div class="pt-2 border-t border-crm-border">
                    <span class="font-bold">Example:</span> $5,000 deal, 3% SNR, 5% VD<br>
                    Payable = $5,000 - $150 - $250 = $4,600<br>
                    Closer (40%) = $1,840 | Fronter (10%) = $460
                </div>
                <div class="text-[10px] text-crm-t3">Rates are set in Settings → Payroll Rules. Changes affect future deals only.</div>
            </div>
        </div>
    @endif
</div>
