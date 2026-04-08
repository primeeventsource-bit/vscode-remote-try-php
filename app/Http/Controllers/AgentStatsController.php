<?php

namespace App\Http\Controllers;

use App\Services\AgentStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentStatsController extends Controller
{
    /**
     * GET /api/agent-stats/summary
     * Stat cards: fronter + closer totals, filterable by role/location/date range.
     */
    public function summary(Request $request): JsonResponse
    {
        $this->authorizeView($request);

        [$from, $to] = $this->parseDateRange($request);

        $data = AgentStatisticsService::summary(
            $request->query('role'),
            $request->query('location'),
            $from,
            $to
        );

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/agent-stats/role-breakdown
     * Rows: Fronter(US), Fronter(Panama), Closer(US), Closer(Panama)
     */
    public function roleBreakdown(Request $request): JsonResponse
    {
        $this->authorizeView($request);

        [$from, $to] = $this->parseDateRange($request);

        $data = AgentStatisticsService::roleBreakdown($from, $to);

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/agent-stats/leaderboard
     * Per-agent ranking by revenue, filterable by role/location/date.
     */
    public function leaderboard(Request $request): JsonResponse
    {
        $this->authorizeView($request);

        [$from, $to] = $this->parseDateRange($request);

        $data = AgentStatisticsService::leaderboard(
            $request->query('role'),
            $request->query('location'),
            $from,
            $to,
            (int) ($request->query('limit', 20))
        );

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/agent-stats/insights
     * AI performance insights: weakest/strongest groups, slow follow-ups, top converters.
     */
    public function performanceInsights(Request $request): JsonResponse
    {
        $this->authorizeView($request);

        [$from, $to] = $this->parseDateRange($request);

        $data = AgentStatisticsService::performanceInsights($from, $to);

        return response()->json(['data' => $data]);
    }

    // ── Helpers ─────────────────────────────────────────────

    private function parseDateRange(Request $request): array
    {
        $from = $request->query('from');
        $to = $request->query('to');
        $range = $request->query('range');

        if ($range) {
            $now = now();
            return match ($range) {
                'daily' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
                'weekly' => [$now->copy()->startOfWeek(), $now->copy()->endOfDay()],
                'monthly' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
                default => [null, null],
            };
        }

        return [$from, $to];
    }

    private function authorizeView(Request $request): void
    {
        $user = $request->user();

        // Master admin and admin see everything
        if ($user->hasRole('master_admin', 'admin')) {
            return;
        }

        // Agents can only see their own stats — enforced at service level
        // but we allow access to summary/leaderboard for motivation
        if (!$user->hasPerm('view_stats') && !$user->hasPerm('view_dashboard')) {
            abort(403, 'Insufficient permissions');
        }
    }
}
