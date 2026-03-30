<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    /**
     * Single endpoint for all payroll actions.
     * GET/POST /api/payroll?action=XXX
     *
     * Replicates the Node.js Azure Functions API 1:1.
     */
    public function handle(Request $request)
    {
        $action = $request->query('action', '');
        $body = $request->all();

        try {
            switch ($action) {

                // ─── SETTINGS ──────────────────────────────────────────────
                case 'get_settings':
                    return $this->getSettings();

                case 'save_settings':
                    return $this->saveSettings($body);

                // ─── USER RATES ────────────────────────────────────────────
                case 'get_user_rates':
                    return $this->getUserRates();

                case 'save_user_rate':
                    return $this->saveUserRate($body);

                case 'delete_user_rate':
                    return $this->deleteUserRate($body);

                // ─── ADMIN HOURS ───────────────────────────────────────────
                case 'get_admin_hours':
                    return $this->getAdminHours($request);

                case 'save_admin_hours':
                    return $this->saveAdminHours($body);

                // ─── DEAL OVERRIDES ────────────────────────────────────────
                case 'save_deal_override':
                    return $this->saveDealOverride($body);

                case 'undo_deal_override':
                    return $this->undoDealOverride($body);

                // ─── MANUAL DEALS ─────────────────────────────────────────
                case 'add_manual_deal':
                    return $this->addManualDeal($body);

                case 'update_manual_deal':
                    return $this->updateManualDeal($body);

                case 'remove_manual_deal':
                    return $this->removeManualDeal($body);

                // ─── NOTES ─────────────────────────────────────────────────
                case 'save_note':
                    return $this->saveNote($body);

                // ─── ENTRIES ───────────────────────────────────────────────
                case 'save_entry':
                    return $this->saveEntry($body);

                case 'delete_entry':
                    return $this->deleteEntry($body);

                // ─── SEND PAYSHEET ─────────────────────────────────────────
                case 'send_paysheet':
                    return $this->sendPaysheet($body);

                case 'get_sent_sheets':
                    return $this->getSentSheets($request);

                // ─── HISTORY ───────────────────────────────────────────────
                case 'get_history':
                    return $this->getHistory($request);

                // ─── EXPORT CSV ────────────────────────────────────────────
                case 'export_csv':
                    return $this->exportCsv($request);

                // ─── LOAD WEEK (bulk) ──────────────────────────────────────
                case 'load_week':
                    return $this->loadWeek($request);

                default:
                    return response()->json(['error' => 'Unknown action: ' . $action], 400);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    // =====================================================================
    // Helper methods — replicate Node.js getWeekMonday / getWeekFriday
    // =====================================================================

    /**
     * Get the Monday of the week for a given date string (YYYY-MM-DD).
     * If no date, uses today. Matches the Node.js UTC-based logic.
     */
    private function getWeekMonday(?string $dateStr = null): string
    {
        if ($dateStr) {
            $d = Carbon::createFromFormat('Y-m-d', $dateStr, 'UTC')->startOfDay();
        } else {
            $d = Carbon::now('UTC')->startOfDay();
        }

        $day = $d->dayOfWeek; // 0=Sunday .. 6=Saturday
        $diff = $day === 0 ? -6 : 1 - $day;
        $d->addDays($diff);

        return $d->format('Y-m-d');
    }

    /**
     * Get Friday (Monday + 4 days).
     */
    private function getWeekFriday(string $monday): string
    {
        return Carbon::createFromFormat('Y-m-d', $monday, 'UTC')
            ->addDays(4)
            ->format('Y-m-d');
    }

    // =====================================================================
    // Action implementations
    // =====================================================================

    private function getSettings()
    {
        $settings = DB::table('payroll_settings')->first();

        if (!$settings) {
            DB::table('payroll_settings')->insert([
                'closer_pct' => 50,
                'fronter_pct' => 10,
                'snr_pct' => 2,
                'vd_pct' => 3,
                'admin_snr_pct' => 2,
                'hourly_rate' => 19.50,
            ]);
            $settings = DB::table('payroll_settings')->first();
        }

        return response()->json(['settings' => $settings]);
    }

    private function saveSettings(array $body)
    {
        DB::table('payroll_settings')->update([
            'closer_pct' => $body['closerPct'] ?? 50,
            'fronter_pct' => $body['fronterPct'] ?? 10,
            'snr_pct' => $body['snrPct'] ?? 2,
            'vd_pct' => $body['vdPct'] ?? 3,
            'admin_snr_pct' => $body['adminSnrPct'] ?? 2,
            'hourly_rate' => $body['hourlyRate'] ?? 19.50,
            'updated_by' => $body['updatedBy'] ?? null,
        ]);

        return response()->json(['ok' => true]);
    }

    private function getUserRates()
    {
        $rows = DB::table('payroll_user_rates')->get();
        $rates = [];

        foreach ($rows as $row) {
            $rates[$row->user_id] = [
                'commPct' => $row->comm_pct !== null ? (float) $row->comm_pct : null,
                'snrPct' => $row->snr_pct !== null ? (float) $row->snr_pct : null,
                'hourlyRate' => $row->hourly_rate !== null ? (float) $row->hourly_rate : null,
            ];
        }

        return response()->json(['userRates' => $rates]);
    }

    private function saveUserRate(array $body)
    {
        if (empty($body['userId'])) {
            return response()->json(['error' => 'userId required'], 400);
        }

        DB::statement("
            MERGE payroll_user_rates AS t
            USING (SELECT ? AS user_id) AS s ON t.user_id = s.user_id
            WHEN MATCHED THEN UPDATE SET comm_pct = ?, snr_pct = ?, hourly_rate = ?
            WHEN NOT MATCHED THEN INSERT (user_id, comm_pct, snr_pct, hourly_rate) VALUES (?, ?, ?, ?);
        ", [
            $body['userId'],
            $body['commPct'] ?? null,
            $body['snrPct'] ?? null,
            $body['hourlyRate'] ?? null,
            $body['userId'],
            $body['commPct'] ?? null,
            $body['snrPct'] ?? null,
            $body['hourlyRate'] ?? null,
        ]);

        return response()->json(['ok' => true]);
    }

    private function deleteUserRate(array $body)
    {
        DB::table('payroll_user_rates')
            ->where('user_id', $body['userId'] ?? '')
            ->delete();

        return response()->json(['ok' => true]);
    }

    private function getAdminHours(Request $request)
    {
        $ws = $request->query('week_start', $this->getWeekMonday());

        $rows = DB::table('payroll_admin_hours')
            ->where('week_start', $ws)
            ->get();

        $hrs = [];
        foreach ($rows as $row) {
            $hrs[$row->user_id] = (float) $row->hours;
        }

        return response()->json(['adminHours' => $hrs]);
    }

    private function saveAdminHours(array $body)
    {
        $ws = $body['weekStart'] ?? $this->getWeekMonday();

        DB::statement("
            MERGE payroll_admin_hours AS t
            USING (SELECT ? AS user_id, ? AS week_start) AS s
                ON t.user_id = s.user_id AND t.week_start = s.week_start
            WHEN MATCHED THEN UPDATE SET hours = ?
            WHEN NOT MATCHED THEN INSERT (user_id, week_start, hours) VALUES (?, ?, ?);
        ", [
            $body['userId'],
            $ws,
            $body['hours'] ?? 0,
            $body['userId'],
            $ws,
            $body['hours'] ?? 0,
        ]);

        return response()->json(['ok' => true]);
    }

    private function saveDealOverride(array $body)
    {
        $ws = $body['weekStart'] ?? $this->getWeekMonday();

        DB::statement("
            MERGE payroll_deal_overrides AS t
            USING (SELECT ? AS user_id, ? AS deal_id, ? AS week_start, ? AS override_type) AS s
                ON t.user_id = s.user_id AND t.deal_id = s.deal_id
                AND t.week_start = s.week_start AND t.override_type = s.override_type
            WHEN MATCHED THEN UPDATE SET override_value = ?
            WHEN NOT MATCHED THEN INSERT (user_id, deal_id, week_start, override_type, override_value)
                VALUES (?, ?, ?, ?, ?);
        ", [
            $body['userId'],
            $body['dealId'],
            $ws,
            $body['type'],
            $body['value'],
            $body['userId'],
            $body['dealId'],
            $ws,
            $body['type'],
            $body['value'],
        ]);

        return response()->json(['ok' => true]);
    }

    private function undoDealOverride(array $body)
    {
        $ws = $body['weekStart'] ?? $this->getWeekMonday();

        DB::table('payroll_deal_overrides')
            ->where('user_id', $body['userId'])
            ->where('deal_id', $body['dealId'])
            ->where('week_start', $ws)
            ->where('override_type', $body['type'])
            ->delete();

        return response()->json(['ok' => true]);
    }

    private function addManualDeal(array $body)
    {
        $ws = $body['weekStart'] ?? $this->getWeekMonday();

        $id = DB::table('payroll_manual_deals')->insertGetId([
            'user_id' => $body['userId'],
            'week_start' => $ws,
            'customer_name' => $body['name'],
            'amount' => $body['amount'],
            'deal_date' => $body['date'] ?? null,
            'was_vd' => $body['vd'] ?? 'No',
            'created_by' => $body['createdBy'] ?? '',
        ]);

        return response()->json(['ok' => true, 'id' => $id]);
    }

    private function updateManualDeal(array $body)
    {
        DB::table('payroll_manual_deals')
            ->where('id', $body['id'])
            ->update([
                'customer_name' => $body['name'],
                'amount' => $body['amount'],
                'deal_date' => $body['date'],
                'was_vd' => $body['vd'] ?? 'No',
            ]);

        return response()->json(['ok' => true]);
    }

    private function removeManualDeal(array $body)
    {
        DB::table('payroll_manual_deals')
            ->where('id', $body['id'])
            ->delete();

        return response()->json(['ok' => true]);
    }

    private function saveNote(array $body)
    {
        $ws = $body['weekStart'] ?? $this->getWeekMonday();

        DB::statement("
            MERGE payroll_notes AS t
            USING (SELECT ? AS user_id, ? AS week_start) AS s
                ON t.user_id = s.user_id AND t.week_start = s.week_start
            WHEN MATCHED THEN UPDATE SET note = ?
            WHEN NOT MATCHED THEN INSERT (user_id, week_start, note, created_by)
                VALUES (?, ?, ?, ?);
        ", [
            $body['userId'],
            $ws,
            $body['note'],
            $body['userId'],
            $ws,
            $body['note'],
            $body['createdBy'] ?? '',
        ]);

        return response()->json(['ok' => true]);
    }

    private function saveEntry(array $body)
    {
        $ws = $body['weekStart'] ?? $this->getWeekMonday();
        $we = $this->getWeekFriday($ws);

        // Ensure payroll run exists for this week
        $existing = DB::table('payroll_runs')->where('week_start', $ws)->first();

        if (!$existing) {
            $rid = DB::table('payroll_runs')->insertGetId([
                'week_start' => $ws,
                'week_end' => $we,
                'created_by' => $body['createdBy'] ?? '',
                'created_at' => now(),
            ]);
        } else {
            $rid = $existing->id;
        }

        $dealsJson = json_encode($body['deals'] ?? []);

        // Upsert the entry using MERGE
        DB::statement("
            MERGE payroll_entries AS t
            USING (SELECT ? AS run_id, ? AS user_id) AS s
                ON t.run_id = s.run_id AND t.user_id = s.user_id
            WHEN MATCHED THEN UPDATE SET
                user_name = ?, user_role = ?, pay_type = ?,
                total_sold = ?, total_payout = ?, vd_taken = ?,
                commission_pct = ?, commission_amount = ?, fronter_cut = ?,
                snr_amount = ?, hourly_hours = ?, hourly_rate = ?, hourly_pay = ?,
                gross_pay = ?, cb_total = ?, net_pay = ?, final_pay = ?,
                deal_count = ?, cb_count = ?, vd_count = ?, deals_json = ?
            WHEN NOT MATCHED THEN INSERT (
                run_id, user_id, user_name, user_role, pay_type,
                total_sold, total_payout, vd_taken,
                commission_pct, commission_amount, fronter_cut,
                snr_amount, hourly_hours, hourly_rate, hourly_pay,
                gross_pay, cb_total, net_pay, final_pay,
                deal_count, cb_count, vd_count, deals_json
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
        ", [
            // USING params
            $rid, $body['userId'],
            // UPDATE params
            $body['userName'] ?? '', $body['userRole'] ?? '', $body['payType'] ?? '',
            $body['totalSold'] ?? 0, $body['totalPayout'] ?? 0, $body['vdTaken'] ?? 0,
            $body['commissionPct'] ?? 0, $body['commissionAmount'] ?? 0, $body['fronterCut'] ?? 0,
            $body['snrAmount'] ?? 0, $body['hourlyHours'] ?? 0, $body['hourlyRate'] ?? 0, $body['hourlyPay'] ?? 0,
            $body['grossPay'] ?? 0, $body['cbTotal'] ?? 0, $body['netPay'] ?? 0, $body['finalPay'] ?? 0,
            $body['dealCount'] ?? 0, $body['cbCount'] ?? 0, $body['vdCount'] ?? 0, $dealsJson,
            // INSERT params
            $rid, $body['userId'],
            $body['userName'] ?? '', $body['userRole'] ?? '', $body['payType'] ?? '',
            $body['totalSold'] ?? 0, $body['totalPayout'] ?? 0, $body['vdTaken'] ?? 0,
            $body['commissionPct'] ?? 0, $body['commissionAmount'] ?? 0, $body['fronterCut'] ?? 0,
            $body['snrAmount'] ?? 0, $body['hourlyHours'] ?? 0, $body['hourlyRate'] ?? 0, $body['hourlyPay'] ?? 0,
            $body['grossPay'] ?? 0, $body['cbTotal'] ?? 0, $body['netPay'] ?? 0, $body['finalPay'] ?? 0,
            $body['dealCount'] ?? 0, $body['cbCount'] ?? 0, $body['vdCount'] ?? 0, $dealsJson,
        ]);

        $entry = DB::table('payroll_entries')
            ->where('run_id', $rid)
            ->where('user_id', $body['userId'])
            ->first();

        return response()->json(['ok' => true, 'entryId' => $entry->id, 'runId' => $rid]);
    }

    private function deleteEntry(array $body)
    {
        DB::table('payroll_entries')->where('id', $body['id'])->delete();

        return response()->json(['ok' => true]);
    }

    private function sendPaysheet(array $body)
    {
        $ws = $body['weekStart'] ?? $this->getWeekMonday();

        // Mark the entry as sent
        DB::statement("
            UPDATE pe
            SET pe.status = 'sent', pe.sent_at = GETDATE(), pe.sent_by = ?
            FROM payroll_entries pe
            JOIN payroll_runs pr ON pe.run_id = pr.id
            WHERE pr.week_start = ? AND pe.user_id = ?
        ", [
            $body['sentBy'],
            $ws,
            $body['userId'],
        ]);

        // Insert into sent history
        $id = DB::table('payroll_sent_history')->insertGetId([
            'user_id' => $body['userId'],
            'user_name' => $body['userName'] ?? '',
            'user_role' => $body['userRole'] ?? '',
            'week_start' => $ws,
            'week_label' => $body['weekLabel'] ?? '',
            'final_pay' => $body['finalPay'] ?? 0,
            'sent_by' => $body['sentBy'] ?? '',
            'entry_snapshot' => json_encode($body['snapshot'] ?? null),
            'sent_at' => now(),
        ]);

        return response()->json(['ok' => true, 'id' => $id]);
    }

    private function getSentSheets(Request $request)
    {
        $ws = $request->query('week_start');

        if ($ws) {
            $rows = DB::table('payroll_sent_history')
                ->where('week_start', $ws)
                ->orderBy('sent_at', 'desc')
                ->get();
        } else {
            $rows = DB::table('payroll_sent_history')
                ->orderBy('sent_at', 'desc')
                ->limit(200)
                ->get();
        }

        return response()->json(['sentSheets' => $rows]);
    }

    private function getHistory(Request $request)
    {
        $uid = $request->query('user_id');
        $lim = (int) ($request->query('limit', 50));

        $query = DB::table('payroll_entries as pe')
            ->join('payroll_runs as pr', 'pe.run_id', '=', 'pr.id')
            ->select('pe.*', 'pr.week_start', 'pr.week_end')
            ->orderBy('pr.week_start', 'desc')
            ->limit($lim);

        if ($uid) {
            $query->where('pe.user_id', $uid);
        }

        $rows = $query->get();

        return response()->json(['history' => $rows]);
    }

    private function exportCsv(Request $request)
    {
        $ws = $request->query('week_start', $this->getWeekMonday());

        $rows = DB::table('payroll_entries as pe')
            ->join('payroll_runs as pr', 'pe.run_id', '=', 'pr.id')
            ->where('pr.week_start', $ws)
            ->select(
                'pe.user_name', 'pe.user_role', 'pe.pay_type',
                'pe.total_sold', 'pe.total_payout', 'pe.commission_amount',
                'pe.hourly_pay', 'pe.gross_pay', 'pe.cb_total',
                'pe.net_pay', 'pe.final_pay', 'pe.deal_count', 'pe.cb_count',
                'pe.status', 'pe.sent_at', 'pr.week_start', 'pr.week_end'
            )
            ->orderBy('pe.pay_type')
            ->orderBy('pe.user_name')
            ->get();

        $csv = "Employee,Role,Type,Total Sold,Payout,Commission,Hourly Pay,Gross Pay,CB Total,Net Pay,Final Pay,Deals,CBs,Status,Sent At,Week Start,Week End\n";

        foreach ($rows as $row) {
            $values = array_values((array) $row);
            $csv .= implode(',', array_map(function ($v) {
                return '"' . str_replace('"', '""', (string) ($v ?? '')) . '"';
            }, $values)) . "\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="payroll_' . $ws . '.csv"',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    private function loadWeek(Request $request)
    {
        $ws = $request->query('week_start', $this->getWeekMonday());

        // Fetch all data in parallel-style (sequential in PHP, but all at once)
        $settings = DB::table('payroll_settings')->first();

        $urRows = DB::table('payroll_user_rates')->get();
        $userRates = [];
        foreach ($urRows as $r) {
            $userRates[$r->user_id] = [
                'commPct' => $r->comm_pct !== null ? (float) $r->comm_pct : null,
                'snrPct' => $r->snr_pct !== null ? (float) $r->snr_pct : null,
                'hourlyRate' => $r->hourly_rate !== null ? (float) $r->hourly_rate : null,
            ];
        }

        $ahRows = DB::table('payroll_admin_hours')->where('week_start', $ws)->get();
        $adminHours = [];
        foreach ($ahRows as $r) {
            $adminHours[$r->user_id] = (float) $r->hours;
        }

        $doRows = DB::table('payroll_deal_overrides')->where('week_start', $ws)->get();
        $dealOverrides = [];
        foreach ($doRows as $r) {
            if (!isset($dealOverrides[$r->user_id])) {
                $dealOverrides[$r->user_id] = [];
            }
            $dealOverrides[$r->user_id][] = [
                'dealId' => $r->deal_id,
                'type' => $r->override_type,
                'value' => $r->override_value,
            ];
        }

        $mdRows = DB::table('payroll_manual_deals')->where('week_start', $ws)->get();
        $manualDeals = [];
        foreach ($mdRows as $r) {
            if (!isset($manualDeals[$r->user_id])) {
                $manualDeals[$r->user_id] = [];
            }
            $manualDeals[$r->user_id][] = [
                'id' => $r->id,
                'name' => $r->customer_name,
                'amount' => (float) $r->amount,
                'date' => $r->deal_date,
                'vd' => $r->was_vd,
            ];
        }

        $nRows = DB::table('payroll_notes')->where('week_start', $ws)->get();
        $notes = [];
        foreach ($nRows as $r) {
            $notes[$r->user_id] = $r->note;
        }

        $shRows = DB::table('payroll_sent_history')
            ->where('week_start', $ws)
            ->orderBy('sent_at', 'desc')
            ->get();
        $sentSheets = [];
        foreach ($shRows as $r) {
            $sentSheets[$r->user_id] = [
                'sentAt' => $r->sent_at,
                'amount' => (float) $r->final_pay,
                'sentBy' => $r->sent_by,
            ];
        }

        $entries = DB::table('payroll_entries as pe')
            ->join('payroll_runs as pr', 'pe.run_id', '=', 'pr.id')
            ->where('pr.week_start', $ws)
            ->select('pe.*')
            ->get();

        return response()->json([
            'settings' => $settings,
            'userRates' => $userRates,
            'adminHours' => $adminHours,
            'dealOverrides' => $dealOverrides,
            'manualDeals' => $manualDeals,
            'notes' => $notes,
            'sentSheets' => $sentSheets,
            'entries' => $entries,
            'weekStart' => $ws,
        ]);
    }
}
