<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * GET /api/dashboard
     * Return summary stats for the dashboard
     */
    public function index(Request $request)
    {
        try {
            // Week boundaries (Monday to Sunday)
            $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();
            $weekEnd = Carbon::now()->endOfWeek(Carbon::SUNDAY)->toDateString();

            // Lead stats
            $totalLeads = Lead::count();
            $leadsThisWeek = Lead::whereBetween('created_at', [$weekStart, $weekEnd . ' 23:59:59'])->count();

            $leadsByDisposition = Lead::select('disposition', DB::raw('COUNT(*) as count'))
                ->groupBy('disposition')
                ->pluck('count', 'disposition');

            // Deal stats
            $totalDeals = Deal::count();

            $dealsByStatus = Deal::select('status', DB::raw('COUNT(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status');

            // Revenue: sum of fee where charged = 'Yes' and charged_back != 'Yes'
            $revenue = Deal::where('charged', 'Yes')
                ->where(function ($q) {
                    $q->where('charged_back', '!=', 'Yes')
                      ->orWhereNull('charged_back');
                })
                ->sum('fee');

            // Active users count
            $activeUsers = User::where('status', 'active')->count();

            return response()->json([
                'totalLeads' => $totalLeads,
                'leadsThisWeek' => $leadsThisWeek,
                'leadsByDisposition' => $leadsByDisposition,
                'totalDeals' => $totalDeals,
                'dealsByStatus' => $dealsByStatus,
                'revenue' => round((float) $revenue, 2),
                'activeUsers' => $activeUsers,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }
}
