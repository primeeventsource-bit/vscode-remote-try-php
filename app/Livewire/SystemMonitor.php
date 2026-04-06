<?php

namespace App\Livewire;

use App\Models\SystemHealthCheck;
use App\Models\SystemIncident;
use App\Services\Monitor\HealthCheckRunner;
use App\Services\Monitor\IncidentManager;
use App\Services\Monitor\RecoveryEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('System Monitor')]
class SystemMonitor extends Component
{
    public string $tab = 'health';
    public string $flashMsg = '';

    public function runChecksNow(): void
    {
        if (! auth()->user()?->hasRole('master_admin')) return;
        HealthCheckRunner::runAll();
        $this->flashMsg = 'Health checks completed';
    }

    public function acknowledgeIncident(int $id): void
    {
        if (! auth()->user()?->hasRole('master_admin', 'admin')) return;
        IncidentManager::acknowledge($id, auth()->id());
    }

    public function resolveIncident(int $id): void
    {
        if (! auth()->user()?->hasRole('master_admin', 'admin')) return;
        IncidentManager::resolve($id, auth()->id(), 'Manually resolved by admin');
    }

    public function retryRecovery(string $component): void
    {
        if (! auth()->user()?->hasRole('master_admin')) return;
        $result = RecoveryEngine::runManualRecovery($component, auth()->id());
        $this->flashMsg = $result['message'] ?? 'Recovery attempted';
    }

    public function render()
    {
        $user = auth()->user();
        if (! $user->hasRole('master_admin', 'admin')) {
            return view('livewire.system-monitor', [
                'health' => [], 'incidents' => collect(), 'summary' => ['open' => 0, 'critical' => 0, 'recent' => collect()],
                'failedJobs' => 0, 'queuePending' => 0, 'recentBeats' => collect(),
            ]);
        }

        // Latest health check per component
        $health = [];
        try {
            if (Schema::hasTable('system_health_checks')) {
                $latest = DB::table('system_health_checks')
                    ->select('component', DB::raw('MAX(id) as latest_id'))
                    ->groupBy('component')
                    ->pluck('latest_id');

                $health = SystemHealthCheck::whereIn('id', $latest)
                    ->get()
                    ->keyBy('component')
                    ->toArray();
            }
        } catch (\Throwable $e) {}

        // Incidents
        $summary = IncidentManager::summary();

        // Queue stats
        $failedJobs = 0;
        $queuePending = 0;
        try {
            $failedJobs = DB::table('failed_jobs')->count();
        } catch (\Throwable $e) {}
        try {
            $queuePending = DB::table('jobs')->count();
        } catch (\Throwable $e) {}

        // Scheduler heartbeats
        $recentBeats = collect();
        try {
            if (Schema::hasTable('scheduler_heartbeats')) {
                $recentBeats = DB::table('scheduler_heartbeats')
                    ->orderByDesc('ran_at')
                    ->limit(10)
                    ->get();
            }
        } catch (\Throwable $e) {}

        return view('livewire.system-monitor', compact(
            'health', 'summary', 'failedJobs', 'queuePending', 'recentBeats'
        ));
    }
}
