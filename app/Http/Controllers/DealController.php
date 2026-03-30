<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use Illuminate\Http\Request;

class DealController extends Controller
{
    /**
     * GET /api/deals
     * List deals with optional filters: status, fronter, closer
     */
    public function index(Request $request)
    {
        try {
            $query = Deal::query();

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->filled('fronter')) {
                $query->where('fronter', $request->input('fronter'));
            }

            if ($request->filled('closer')) {
                $query->where('closer', $request->input('closer'));
            }

            $deals = $query->orderBy('created_at', 'desc')->get();

            return response()->json(['deals' => $deals]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/deals
     * Create a new deal
     */
    public function store(Request $request)
    {
        try {
            $data = $request->only([
                'timestamp', 'charged_date', 'was_vd', 'fronter', 'closer',
                'fee', 'owner_name', 'mailing_address', 'city_state_zip',
                'primary_phone', 'secondary_phone', 'email', 'weeks',
                'asking_rental', 'resort_name', 'resort_city_state',
                'exchange_group', 'bed_bath', 'usage', 'asking_sale_price',
                'name_on_card', 'card_type', 'bank', 'card_number',
                'exp_date', 'cv2', 'billing_address', 'bank2',
                'card_number2', 'exp_date2', 'cv2_2', 'using_timeshare',
                'looking_to_get_out', 'verification_num', 'notes',
                'login_info', 'correspondence', 'files', 'snr', 'login',
                'merchant', 'app_login', 'assigned_admin', 'status',
                'charged', 'charged_back',
            ]);

            $deal = Deal::create($data);

            return response()->json(['deal' => $deal], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * PUT /api/deals/{id}
     * Update a deal
     */
    public function update(Request $request, $id)
    {
        try {
            $deal = Deal::find($id);

            if (!$deal) {
                return response()->json(['error' => 'Deal not found'], 404);
            }

            $data = $request->only([
                'timestamp', 'charged_date', 'was_vd', 'fronter', 'closer',
                'fee', 'owner_name', 'mailing_address', 'city_state_zip',
                'primary_phone', 'secondary_phone', 'email', 'weeks',
                'asking_rental', 'resort_name', 'resort_city_state',
                'exchange_group', 'bed_bath', 'usage', 'asking_sale_price',
                'name_on_card', 'card_type', 'bank', 'card_number',
                'exp_date', 'cv2', 'billing_address', 'bank2',
                'card_number2', 'exp_date2', 'cv2_2', 'using_timeshare',
                'looking_to_get_out', 'verification_num', 'notes',
                'login_info', 'correspondence', 'files', 'snr', 'login',
                'merchant', 'app_login', 'assigned_admin', 'status',
                'charged', 'charged_back',
            ]);

            $deal->update($data);

            return response()->json(['deal' => $deal]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * PUT /api/deals/{id}/charge
     * Toggle charged status
     */
    public function toggleCharged(Request $request, $id)
    {
        try {
            $deal = Deal::find($id);

            if (!$deal) {
                return response()->json(['error' => 'Deal not found'], 404);
            }

            $charged = $request->input('charged', $deal->charged === 'Yes' ? 'No' : 'Yes');
            $deal->update([
                'charged' => $charged,
                'charged_date' => $charged === 'Yes' ? now()->toDateString() : null,
            ]);

            return response()->json(['deal' => $deal]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * PUT /api/deals/{id}/chargeback
     * Toggle chargeback status
     */
    public function toggleChargeback(Request $request, $id)
    {
        try {
            $deal = Deal::find($id);

            if (!$deal) {
                return response()->json(['error' => 'Deal not found'], 404);
            }

            $chargedBack = $request->input('charged_back', $deal->charged_back === 'Yes' ? 'No' : 'Yes');
            $deal->update(['charged_back' => $chargedBack]);

            return response()->json(['deal' => $deal]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /api/deals/{id}
     * Delete a deal
     */
    public function destroy($id)
    {
        try {
            $deal = Deal::find($id);

            if (!$deal) {
                return response()->json(['error' => 'Deal not found'], 404);
            }

            $deal->delete();

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }
}
