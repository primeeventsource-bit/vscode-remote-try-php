<div class="p-5">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-bold">System Monitor</h2>
            <p class="text-xs text-crm-t3 mt-0.5">Health checks, incidents, and recovery status</p>
        </div>
        @if(auth()->user()->hasRole('master_admin'))
            <button wire:click="runChecksNow" class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">
                Run Checks Now
            </button>
        @endif
    </div>

    @if($flashMsg)
        <div class="mb-4 px-4 py-2.5 rounded-lg text-sm font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">{{ $flashMsg }}</div>
    @endif

    {{-- Tabs --}}
    <div class="flex gap-1 bg-crm-card border border-crm-border rounded-lg p-0.5 mb-5">
        @foreach(['healing' => 'Self-Healing', 'health' => 'System Health', 'storage' => 'Storage', 'incidents' => 'Incidents ('.$summary['open'].')', 'queue' => 'Queue & Jobs', 'scheduler' => 'Scheduler'] as $k => $l)
            <button wire:click="$set('tab', '{{ $k }}')" class="px-3 py-1.5 text-xs font-semibold rounded-md transition {{ $tab === $k ? 'bg-white text-blue-600 shadow-sm' : 'text-crm-t3 hover:text-crm-t1' }}">{{ $l }}</button>
        @endforeach
    </div>

    {{-- ═══ SELF-HEALING ═══ --}}
    @if($tab === 'healing')
        @php
            $hs = $healingSummary ?? [];
            $overallState = $hs['overall'] ?? 'unknown';
            $overallColor = match($overallState) {
                'healthy' => 'emerald', 'degraded' => 'amber', 'critical', 'failed' => 'red',
                'failover' => 'blue', default => 'gray',
            };
            $subsystems = [
                'queue' => ['label' => 'Queue', 'icon' => '⚡'],
                'scheduler' => ['label' => 'Scheduler', 'icon' => '⏱'],
                'storage' => ['label' => 'Storage', 'icon' => '💾'],
            ];
        @endphp

        {{-- Overall Banner --}}
        <div class="bg-{{ $overallColor }}-50 border border-{{ $overallColor }}-200 rounded-lg p-4 mb-5 flex items-center justify-between">
            <div>
                <div class="text-sm font-bold text-{{ $overallColor }}-800">Platform: {{ strtoupper(str_replace('_', ' ', $overallState)) }}</div>
                <div class="text-[10px] text-{{ $overallColor }}-600 mt-0.5">Queue + Scheduler + Storage unified health</div>
            </div>
            @if(auth()->user()->hasRole('master_admin'))
                <button wire:click="runFullHeal" class="px-4 py-1.5 text-xs font-semibold text-white bg-{{ $overallColor }}-600 rounded-lg hover:bg-{{ $overallColor }}-700 transition">
                    Run Full Heal
                </button>
            @endif
        </div>

        {{-- Subsystem Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
            @foreach($subsystems as $key => $meta)
                @php
                    $subState = $hs[$key] ?? 'unknown';
                    $subColor = match($subState) {
                        'healthy' => 'emerald', 'degraded', 'lagging' => 'amber', 'stuck' => 'amber',
                        'critical', 'failed', 'down' => 'red', 'failover_active' => 'blue', default => 'gray',
                    };
                @endphp
                <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-l-4 border-l-{{ $subColor }}-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-xs font-bold">{{ $meta['icon'] }} {{ $meta['label'] }}</div>
                            <div class="text-lg font-extrabold text-{{ $subColor }}-600 mt-1">{{ ucfirst(str_replace('_', ' ', $subState)) }}</div>
                        </div>
                        <span class="text-[9px] font-bold px-2 py-1 rounded bg-{{ $subColor }}-50 text-{{ $subColor }}-700 uppercase">{{ $subState }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Recent Healing Actions --}}
        @php $actions = $hs['actions'] ?? collect(); @endphp
        @if($actions instanceof \Illuminate\Support\Collection ? $actions->isNotEmpty() : !empty($actions))
            <div class="text-xs font-bold mb-2">Recent Healing Actions (24h)</div>
            <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-crm-border bg-crm-surface">
                            <th class="text-left px-4 py-2 text-[10px] uppercase text-crm-t3 font-semibold">Subsystem</th>
                            <th class="text-left px-3 py-2 text-[10px] uppercase text-crm-t3 font-semibold">Action</th>
                            <th class="text-center px-3 py-2 text-[10px] uppercase text-crm-t3 font-semibold">Status</th>
                            <th class="text-left px-3 py-2 text-[10px] uppercase text-crm-t3 font-semibold">Trigger</th>
                            <th class="text-right px-4 py-2 text-[10px] uppercase text-crm-t3 font-semibold">When</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($actions as $act)
                            @php $actColor = match($act->status ?? '') { 'success' => 'emerald', 'failed' => 'red', 'running' => 'blue', default => 'gray' }; @endphp
                            <tr class="border-b border-crm-border">
                                <td class="px-4 py-2 text-xs font-mono">{{ $act->subsystem }}</td>
                                <td class="px-3 py-2 text-xs">{{ $act->action }}</td>
                                <td class="text-center px-3 py-2"><span class="text-[9px] font-bold px-2 py-0.5 rounded bg-{{ $actColor }}-50 text-{{ $actColor }}-600">{{ $act->status }}</span></td>
                                <td class="px-3 py-2 text-[10px] text-crm-t3">{{ $act->trigger }}</td>
                                <td class="text-right px-4 py-2 text-[10px] text-crm-t3">{{ $act->created_at?->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-xs text-crm-t3 text-center py-6">No healing actions in the last 24 hours — system stable.</p>
        @endif
    @endif

    {{-- ═══ HEALTH ═══ --}}
    @if($tab === 'health')
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            @php
                $components = ['app', 'database', 'queue', 'storage', 'chat', 'scheduler', 'security'];
                $statusColors = ['healthy' => 'emerald', 'degraded' => 'amber', 'critical' => 'red', 'unknown' => 'gray'];
            @endphp
            @foreach($components as $comp)
                @php
                    $check = $health[$comp] ?? null;
                    $status = $check['status'] ?? 'unknown';
                    $color = $statusColors[$status] ?? 'gray';
                    $details = is_string($check['details'] ?? null) ? json_decode($check['details'], true) : ($check['details'] ?? []);
                @endphp
                <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-{{ $color }}-500">
                    <div class="flex items-center justify-between mb-1">
                        <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">{{ ucfirst($comp) }}</div>
                        <span class="text-[9px] font-bold px-2 py-0.5 rounded bg-{{ $color }}-50 text-{{ $color }}-700 uppercase">{{ $status }}</span>
                    </div>
                    @if($check)
                        <div class="text-[10px] text-crm-t3 mt-1">{{ $check['response_time_ms'] ?? '-' }}ms</div>
                        @if(!empty($details))
                            <div class="mt-2 space-y-0.5">
                                @foreach(array_slice($details, 0, 3) as $k => $v)
                                    <div class="text-[9px] text-crm-t3"><span class="font-semibold">{{ $k }}:</span> {{ is_array($v) ? implode(', ', $v) : $v }}</div>
                                @endforeach
                            </div>
                        @endif
                    @else
                        <div class="text-[10px] text-crm-t3 mt-1">No data — run checks</div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- ═══ STORAGE ═══ --}}
    @if($tab === 'storage')
        @php $ss = $storageStatus; @endphp
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            @php
                $stateColor = match($ss?->state ?? 'unknown') {
                    'healthy' => 'emerald', 'degraded' => 'amber', 'failed' => 'red', 'failover_active' => 'blue', default => 'gray'
                };
            @endphp
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-{{ $stateColor }}-500">
                <div class="text-[10px] text-crm-t3 uppercase">State</div>
                <div class="text-lg font-extrabold text-{{ $stateColor }}-600 mt-1">{{ ucfirst(str_replace('_', ' ', $ss?->state ?? 'unknown')) }}</div>
            </div>
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-blue-500">
                <div class="text-[10px] text-crm-t3 uppercase">Active Disk</div>
                <div class="text-lg font-extrabold text-blue-600 mt-1">{{ $ss?->active_disk ?? '-' }}</div>
                @if($ss?->forced_disk) <div class="text-[9px] text-amber-600 font-bold">FORCED</div> @endif
            </div>
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] {{ ($ss?->primary_healthy ?? true) ? 'border-t-emerald-500' : 'border-t-red-500' }}">
                <div class="text-[10px] text-crm-t3 uppercase">Primary ({{ $ss?->primary_disk ?? '-' }})</div>
                <div class="text-sm font-bold {{ ($ss?->primary_healthy ?? true) ? 'text-emerald-600' : 'text-red-600' }} mt-1">
                    {{ ($ss?->primary_healthy ?? true) ? 'Healthy' : 'Unhealthy' }}
                </div>
                <div class="text-[9px] text-crm-t3">{{ $ss?->primary_latency_ms ?? '-' }}ms</div>
            </div>
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] {{ ($ss?->fallback_healthy ?? true) ? 'border-t-emerald-500' : 'border-t-red-500' }}">
                <div class="text-[10px] text-crm-t3 uppercase">Fallback ({{ $ss?->fallback_disk ?? '-' }})</div>
                <div class="text-sm font-bold {{ ($ss?->fallback_healthy ?? true) ? 'text-emerald-600' : 'text-red-600' }} mt-1">
                    {{ ($ss?->fallback_healthy ?? true) ? 'Healthy' : 'Unhealthy' }}
                </div>
                <div class="text-[9px] text-crm-t3">{{ $ss?->fallback_latency_ms ?? '-' }}ms</div>
            </div>
        </div>

        {{-- Admin Controls --}}
        @if(auth()->user()->hasRole('master_admin'))
            <div class="flex flex-wrap gap-2 mb-4">
                <button wire:click="runStorageCheck" class="px-3 py-1.5 text-xs font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700">Run Check Now</button>
                <button wire:click="forceStorageDisk('primary')" class="px-3 py-1.5 text-xs font-semibold text-crm-t2 bg-gray-100 rounded-lg hover:bg-gray-200">Force Primary</button>
                <button wire:click="forceStorageDisk('fallback')" class="px-3 py-1.5 text-xs font-semibold text-crm-t2 bg-gray-100 rounded-lg hover:bg-gray-200">Force Fallback</button>
                <button wire:click="forceStorageDisk('auto')" class="px-3 py-1.5 text-xs font-semibold text-emerald-700 bg-emerald-50 rounded-lg hover:bg-emerald-100">Auto Mode</button>
            </div>
        @endif

        {{-- Info --}}
        <div class="text-[10px] text-crm-t3 mb-4 space-y-0.5">
            <div>Last check: {{ $ss?->last_checked_at?->diffForHumans() ?? 'Never' }}</div>
            <div>Failures: {{ $ss?->failure_count ?? 0 }} · Recoveries: {{ $ss?->recovery_count ?? 0 }}</div>
            @if($ss?->last_failover_at) <div>Last failover: {{ $ss->last_failover_at->diffForHumans() }}</div> @endif
            @if($ss?->last_recovery_at) <div>Last recovery: {{ $ss->last_recovery_at->diffForHumans() }}</div> @endif
        </div>

        {{-- Recent Events --}}
        @if($storageEvents->isNotEmpty())
            <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-crm-border bg-crm-surface">
                            <th class="text-left px-4 py-2 text-[10px] uppercase text-crm-t3 font-semibold">Event</th>
                            <th class="text-left px-3 py-2 text-[10px] uppercase text-crm-t3 font-semibold">Message</th>
                            <th class="text-center px-3 py-2 text-[10px] uppercase text-crm-t3 font-semibold">Severity</th>
                            <th class="text-right px-4 py-2 text-[10px] uppercase text-crm-t3 font-semibold">When</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($storageEvents as $evt)
                            @php $sevColor = match($evt->severity) { 'critical' => 'red', 'warning' => 'amber', default => 'gray' }; @endphp
                            <tr class="border-b border-crm-border">
                                <td class="px-4 py-2 text-xs font-mono">{{ $evt->event_type }}</td>
                                <td class="px-3 py-2 text-xs truncate max-w-[200px]">{{ $evt->message }}</td>
                                <td class="text-center px-3 py-2"><span class="text-[9px] font-bold px-2 py-0.5 rounded bg-{{ $sevColor }}-50 text-{{ $sevColor }}-600">{{ $evt->severity }}</span></td>
                                <td class="text-right px-4 py-2 text-xs text-crm-t3">{{ $evt->created_at?->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-xs text-crm-t3 text-center py-6">No storage events recorded yet. Run a health check to start monitoring.</p>
        @endif
    @endif

    {{-- ═══ INCIDENTS ═══ --}}
    @if($tab === 'incidents')
        @if($summary['recent']->isEmpty())
            <div class="bg-crm-card border border-crm-border rounded-lg p-12 text-center">
                <div class="text-3xl mb-2">✓</div>
                <div class="text-sm font-bold text-emerald-600">No incidents</div>
            </div>
        @else
            <div class="space-y-2">
                @foreach($summary['recent'] as $incident)
                    @php
                        $sevColor = match($incident->severity) {
                            'critical', 'system_breaking' => 'red',
                            'warning' => 'amber',
                            default => 'gray',
                        };
                        $isOpen = in_array($incident->status, ['open', 'acknowledged']);
                    @endphp
                    <div class="bg-crm-card border border-crm-border rounded-lg px-4 py-3 {{ $isOpen ? 'border-l-4 border-l-'.$sevColor.'-500' : '' }}">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-bold">{{ $incident->title }}</span>
                                    <span class="text-[8px] font-bold px-1.5 py-0.5 rounded bg-{{ $sevColor }}-50 text-{{ $sevColor }}-700 uppercase">{{ $incident->severity }}</span>
                                    <span class="text-[8px] font-bold px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 uppercase">{{ $incident->status }}</span>
                                </div>
                                <div class="text-[10px] text-crm-t3 mt-0.5">{{ $incident->component }} · {{ $incident->opened_at }}</div>
                            </div>
                            @if($isOpen && auth()->user()->hasRole('master_admin', 'admin'))
                                <div class="flex gap-1">
                                    @if($incident->status === 'open')
                                        <button wire:click="acknowledgeIncident({{ $incident->id }})" class="px-2 py-1 text-[10px] font-semibold text-blue-600 bg-blue-50 rounded hover:bg-blue-100">Acknowledge</button>
                                    @endif
                                    <button wire:click="resolveIncident({{ $incident->id }})" class="px-2 py-1 text-[10px] font-semibold text-emerald-600 bg-emerald-50 rounded hover:bg-emerald-100">Resolve</button>
                                    <button wire:click="retryRecovery('{{ $incident->component }}')" class="px-2 py-1 text-[10px] font-semibold text-amber-600 bg-amber-50 rounded hover:bg-amber-100">Retry Fix</button>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif

    {{-- ═══ QUEUE ═══ --}}
    @if($tab === 'queue')
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-blue-500">
                <div class="text-[10px] text-crm-t3 uppercase">Pending Jobs</div>
                <div class="text-2xl font-extrabold text-blue-500 mt-1">{{ $queuePending }}</div>
            </div>
            <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] {{ $failedJobs > 0 ? 'border-t-red-500' : 'border-t-emerald-500' }}">
                <div class="text-[10px] text-crm-t3 uppercase">Failed Jobs</div>
                <div class="text-2xl font-extrabold {{ $failedJobs > 0 ? 'text-red-500' : 'text-emerald-500' }} mt-1">{{ $failedJobs }}</div>
                @if($failedJobs > 0 && auth()->user()->hasRole('master_admin'))
                    <button wire:click="retryRecovery('queue')" class="mt-2 px-3 py-1 text-[10px] font-semibold text-white bg-red-500 rounded hover:bg-red-600">Retry All</button>
                @endif
            </div>
        </div>
        <div class="text-xs text-crm-t3">Queue driver: <span class="font-mono font-bold">{{ config('queue.default') }}</span></div>
    @endif

    {{-- ═══ SCHEDULER ═══ --}}
    @if($tab === 'scheduler')
        @if($recentBeats->isEmpty())
            <div class="bg-crm-card border border-crm-border rounded-lg p-8 text-center">
                <div class="text-sm text-crm-t3">No scheduler heartbeats recorded yet.</div>
                <div class="text-xs text-crm-t3 mt-1">Ensure cron is running: <code class="bg-gray-100 px-1 rounded">* * * * * php artisan schedule:run</code></div>
            </div>
        @else
            <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-crm-border bg-crm-surface">
                            <th class="text-left px-4 py-2 text-[10px] uppercase text-crm-t3 font-semibold">Command</th>
                            <th class="text-center px-3 py-2 text-[10px] uppercase text-crm-t3 font-semibold">Status</th>
                            <th class="text-center px-3 py-2 text-[10px] uppercase text-crm-t3 font-semibold">Duration</th>
                            <th class="text-right px-4 py-2 text-[10px] uppercase text-crm-t3 font-semibold">Ran At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentBeats as $beat)
                            <tr class="border-b border-crm-border">
                                <td class="px-4 py-2 font-mono text-xs">{{ $beat->command }}</td>
                                <td class="text-center px-3 py-2">
                                    <span class="text-[9px] font-bold px-2 py-0.5 rounded {{ $beat->status === 'success' ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600' }}">{{ $beat->status }}</span>
                                </td>
                                <td class="text-center px-3 py-2 text-xs text-crm-t3">{{ $beat->duration_ms ?? '-' }}ms</td>
                                <td class="text-right px-4 py-2 text-xs text-crm-t3">{{ $beat->ran_at }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</div>
