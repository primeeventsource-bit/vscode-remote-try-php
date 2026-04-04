<?php

namespace App\Http\Controllers;

use App\Models\CallLog;
use App\Models\CrmFileActivityLog;
use App\Support\PhoneDialer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DialerController extends Controller
{
    public function prepare(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'phone' => 'required|string|max:50',
                'name' => 'nullable|string|max:255',
                'record_type' => 'nullable|string|max:50',
                'record_id' => 'nullable|integer',
            ]);

            $phone = $request->input('phone');
            $normalized = PhoneDialer::normalize($phone);

            if (!$normalized || !PhoneDialer::isValid($phone)) {
                return response()->json(['success' => false, 'message' => 'Invalid phone number.'], 422);
            }

            // Get dialer settings
            $mode = $this->setting('dialer.mode', 'tel');
            $domain = $this->setting('dialer.sip_domain', '');
            $prefix = $this->setting('dialer.trunk_prefix', '');

            $href = PhoneDialer::generateHref($phone, $mode, $domain, $prefix);
            $extension = PhoneDialer::extractExtension($phone);

            // Log the call
            $log = null;
            if ($this->setting('dialer.logging_enabled', true)) {
                $log = CallLog::create([
                    'user_id' => auth()->id(),
                    'record_type' => $request->input('record_type'),
                    'record_id' => $request->input('record_id'),
                    'contact_name' => $request->input('name'),
                    'raw_phone' => $phone,
                    'normalized_phone' => $normalized,
                    'extension' => $extension,
                    'launch_method' => $mode,
                    'generated_href' => $href,
                    'status' => 'initiated',
                    'initiated_at' => now(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                try {
                    CrmFileActivityLog::log('calls', $log->id, 'call_initiated', [
                        'phone' => $normalized,
                        'name' => $request->input('name'),
                    ]);
                } catch (\Throwable $e) {}
            }

            return response()->json([
                'success' => true,
                'href' => $href,
                'call_log_id' => $log?->id,
                'normalized_phone' => $normalized,
                'extension' => $extension,
                'display_phone' => PhoneDialer::formattedDisplay($phone),
                'mode' => $mode,
                'require_outcome' => (bool) $this->setting('dialer.require_outcome', false),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Dialer error: ' . $e->getMessage()], 500);
        }
    }

    public function saveOutcome(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'call_log_id' => 'required|integer',
                'outcome' => 'required|string|in:connected,no_answer,voicemail,wrong_number,busy,callback,closed_sale,follow_up_needed,failed_launch',
                'notes' => 'nullable|string',
                'duration_seconds' => 'nullable|integer|min:0',
            ]);

            $log = CallLog::find($request->input('call_log_id'));
            if (!$log) {
                return response()->json(['success' => false, 'message' => 'Call log not found.'], 404);
            }

            $outcome = $request->input('outcome');
            $status = in_array($outcome, ['failed_launch']) ? 'failed' : 'completed';

            $log->update([
                'outcome' => $outcome,
                'status' => $status,
                'notes' => $request->input('notes'),
                'duration_seconds' => $request->input('duration_seconds'),
                'ended_at' => now(),
            ]);

            return response()->json(['success' => true, 'message' => 'Call outcome saved.']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        try {
            $raw = DB::table('crm_settings')->where('key', $key)->value('value');
            return $raw === null ? $default : json_decode($raw, true);
        } catch (\Throwable $e) {
            return $default;
        }
    }
}
