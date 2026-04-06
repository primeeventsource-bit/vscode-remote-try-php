<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DealController extends Controller
{
    private const SAFE_FIELDS = [
        'timestamp', 'charged_date', 'was_vd', 'fronter', 'closer',
        'fee', 'owner_name', 'mailing_address', 'city_state_zip',
        'primary_phone', 'secondary_phone', 'email', 'weeks',
        'asking_rental', 'resort_name', 'resort_city_state',
        'exchange_group', 'bed_bath', 'usage', 'asking_sale_price',
        'name_on_card', 'card_type', 'bank', 'billing_address',
        'bank2', 'using_timeshare', 'looking_to_get_out',
        'verification_num', 'notes', 'correspondence',
        'snr', 'login', 'merchant', 'app_login',
    ];

    // Fields that only admins can modify
    private const ADMIN_FIELDS = [
        'status', 'charged', 'charged_back', 'assigned_admin',
        'card_number', 'exp_date', 'cv2', 'card_number2', 'exp_date2', 'cv2_2',
        'login_info', 'files',
    ];

    public function index(Request $request)
    {
        $query = Deal::query();
        $user = $request->user();

        // Non-admin users see only their deals
        if (! $user->hasRole('master_admin', 'admin')) {
            $query->where(function ($q) use ($user) {
                $q->where('fronter', $user->name)
                  ->orWhere('closer', $user->name)
                  ->orWhere('assigned_admin', $user->id);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('fronter')) {
            $query->where('fronter', $request->input('fronter'));
        }
        if ($request->filled('closer')) {
            $query->where('closer', $request->input('closer'));
        }

        return response()->json($query->orderBy('created_at', 'desc')->paginate(50));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'owner_name'   => 'required|string|max:255',
            'fee'          => 'nullable|numeric|min:0',
            'fronter'      => 'nullable|string|max:255',
            'closer'       => 'nullable|string|max:255',
            'resort_name'  => 'nullable|string|max:255',
            'primary_phone' => 'nullable|string|max:50',
            'email'        => 'nullable|email|max:255',
            'status'       => 'nullable|string|max:50',
        ]);

        // Non-admins cannot set sensitive fields
        $allowedKeys = self::SAFE_FIELDS;
        if ($request->user()->hasRole('master_admin', 'admin')) {
            $allowedKeys = array_merge($allowedKeys, self::ADMIN_FIELDS);
        }

        $filtered = array_intersect_key(
            $request->only($allowedKeys),
            array_flip($allowedKeys)
        );

        $deal = DB::transaction(fn () => Deal::create($filtered));

        return response()->json(['deal' => $deal], 201);
    }

    public function update(Request $request, $id)
    {
        $deal = Deal::findOrFail($id);

        $allowedKeys = self::SAFE_FIELDS;
        if ($request->user()->hasRole('master_admin', 'admin')) {
            $allowedKeys = array_merge($allowedKeys, self::ADMIN_FIELDS);
        }

        $data = array_intersect_key(
            $request->only($allowedKeys),
            array_flip($allowedKeys)
        );

        DB::transaction(fn () => $deal->update($data));

        return response()->json(['deal' => $deal->fresh()]);
    }

    public function toggleCharged(Request $request, $id)
    {
        $deal = Deal::findOrFail($id);

        $charged = $request->input('charged', $deal->charged === 'Yes' ? 'No' : 'Yes');

        DB::transaction(function () use ($deal, $charged) {
            $deal->update([
                'charged'      => $charged,
                'charged_date' => $charged === 'Yes' ? now()->toDateString() : null,
            ]);
        });

        return response()->json(['deal' => $deal->fresh()]);
    }

    public function toggleChargeback(Request $request, $id)
    {
        $deal = Deal::findOrFail($id);

        $chargedBack = $request->input('charged_back', $deal->charged_back === 'Yes' ? 'No' : 'Yes');

        $deal->update(['charged_back' => $chargedBack]);

        return response()->json(['deal' => $deal->fresh()]);
    }

    public function destroy($id)
    {
        $deal = Deal::findOrFail($id);
        $deal->delete();

        return response()->json(['ok' => true]);
    }
}
