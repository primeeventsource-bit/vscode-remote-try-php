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
        @foreach(['health' => 'System Health', 'incidents' => 'Incidents ('.$summary['open'].')', 'queue' => 'Queue & Jobs', 'scheduler' => 'Scheduler'] as $k => $l)
            <button wire:click="$set('tab', '{{ $k }}')" class="px-3 py-1.5 text-xs font-semibold rounded-md transition {{ $tab === $k ? 'bg-white text-blue-600 shadow-sm' : 'text-crm-t3 hover:text-crm-t1' }}">{{ $l }}</button>
        @endforeach
    </div>

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
