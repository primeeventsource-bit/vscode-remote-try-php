<?php

namespace App\Services\Monitor;

use App\Models\SystemIncident;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class IncidentManager
{
    /**
     * Open a new incident if one with the same fingerprint isn't already open.
     */
    public static function openIfNew(string $component, string $severity, string $title, array $context = []): ?SystemIncident
    {
        try {
            if (! Schema::hasTable('system_incidents')) return null;

            $fingerprint = md5($component . ':' . $title);

            // Check for existing open incident with same fingerprint
            $existing = SystemIncident::where('fingerprint', $fingerprint)
                ->whereIn('status', ['open', 'acknowledged'])
                ->first();

            if ($existing) {
                // Update context but don't duplicate
                $existing->update(['context' => $context]);
                return $existing;
            }

            $incident = SystemIncident::create([
                'component'   => $component,
                'severity'    => $severity,
                'title'       => $title,
                'description' => json_encode($context),
                'context'     => $context,
                'status'      => 'open',
                'fingerprint' => $fingerprint,
                'opened_at'   => now(),
            ]);

            Log::channel('stderr')->warning("Incident opened: [{$severity}] {$title}", $context);

            // Trigger auto-recovery for safe actions
            RecoveryEngine::attemptAutoRecovery($incident);

            return $incident;
        } catch (\Throwable $e) {
            Log::error('Failed to open incident', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public static function resolve(int $incidentId, ?int $userId = null, string $notes = 'Auto-resolved'): void
    {
        try {
            $incident = SystemIncident::find($incidentId);
            if (! $incident) return;

            $incident->update([
                'status'           => $userId ? 'resolved' : 'auto_resolved',
                'resolved_by'      => $userId,
                'resolved_at'      => now(),
                'resolution_notes' => $notes,
            ]);
        } catch (\Throwable $e) {}
    }

    public static function acknowledge(int $incidentId, int $userId): void
    {
        try {
            SystemIncident::where('id', $incidentId)->update([
                'status'          => 'acknowledged',
                'assigned_to'     => $userId,
                'acknowledged_at' => now(),
            ]);
        } catch (\Throwable $e) {}
    }

    /**
     * Get summary for dashboard.
     */
    public static function summary(): array
    {
        try {
            if (! Schema::hasTable('system_incidents')) {
                return ['open' => 0, 'critical' => 0, 'recent' => collect()];
            }

            return [
                'open'     => SystemIncident::open()->count(),
                'critical' => SystemIncident::open()->critical()->count(),
                'recent'   => SystemIncident::orderByDesc('opened_at')->limit(10)->get(),
            ];
        } catch (\Throwable $e) {
            return ['open' => 0, 'critical' => 0, 'recent' => collect()];
        }
    }
}
