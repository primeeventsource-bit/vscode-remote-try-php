<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Communication;
use App\Models\CommunicationEvent;
use App\Models\CommunicationThread;
use App\Models\ContactConsentLog;
use App\Models\Lead;
use App\Models\TwilioWebhookLog;
use App\Support\Phone\PhoneNumberNormalizer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TwilioWebhookController extends Controller
{
    /**
     * POST /webhooks/twilio/messages/inbound
     * Handles incoming SMS from Twilio.
     */
    public function inboundMessage(Request $request): Response
    {
        $payload = $request->all();

        // Log raw webhook
        TwilioWebhookLog::record('messages/inbound', $payload, true, $request->ip());

        $from = $payload['From'] ?? null;
        $to = $payload['To'] ?? null;
        $body = $payload['Body'] ?? '';
        $sid = $payload['MessageSid'] ?? null;
        $numMedia = (int) ($payload['NumMedia'] ?? 0);

        if (! $from || ! $sid) {
            return response('', 204);
        }

        // Idempotency check
        if (Communication::where('provider_message_sid', $sid)->exists()) {
            return response('', 204);
        }

        $normalizedFrom = PhoneNumberNormalizer::normalize($from);

        // Check for STOP/START/HELP keywords
        $keyword = strtoupper(trim($body));
        if (in_array($keyword, ['STOP', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT'])) {
            $this->handleOptOut($normalizedFrom ?? $from, $body);
            return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200)
                ->header('Content-Type', 'text/xml');
        }
        if (in_array($keyword, ['START', 'YES', 'UNSTOP'])) {
            $this->handleOptIn($normalizedFrom ?? $from, $body);
            return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200)
                ->header('Content-Type', 'text/xml');
        }
        if ($keyword === 'HELP') {
            $helpText = config('twilio.help_autoreply_text', 'Reply STOP to unsubscribe.');
            return response('<?xml version="1.0" encoding="UTF-8"?><Response><Message>' . e($helpText) . '</Message></Response>', 200)
                ->header('Content-Type', 'text/xml');
        }

        // Find matching entity by phone
        $entity = null;
        $entityType = null;
        if ($normalizedFrom) {
            $lead = Lead::where('phone1', 'like', '%' . substr($normalizedFrom, -10))
                ->orWhere('phone2', 'like', '%' . substr($normalizedFrom, -10))
                ->first();
            if ($lead) {
                $entity = $lead;
                $entityType = get_class($lead);
            }
        }

        // Find or create thread
        $thread = null;
        if ($entity) {
            $thread = CommunicationThread::firstOrCreate(
                [
                    'threadable_type' => $entityType,
                    'threadable_id'   => $entity->getKey(),
                    'channel'         => 'sms',
                    'phone_number'    => $normalizedFrom ?? $from,
                ],
                ['status' => 'open']
            );
            $thread->increment('unread_count');
            $thread->update([
                'last_message_at' => now(),
                'last_inbound_at' => now(),
            ]);
        }

        // Collect media URLs
        $mediaUrls = [];
        for ($i = 0; $i < $numMedia; $i++) {
            if (isset($payload["MediaUrl{$i}"])) {
                $mediaUrls[] = $payload["MediaUrl{$i}"];
            }
        }

        // Create communication record
        Communication::create([
            'thread_id'            => $thread?->id,
            'communicable_type'    => $entityType,
            'communicable_id'      => $entity?->getKey(),
            'provider'             => 'twilio',
            'provider_message_sid' => $sid,
            'channel'              => $numMedia > 0 ? 'mms' : 'sms',
            'direction'            => 'inbound',
            'message_type'         => $numMedia > 0 ? 'media' : 'text',
            'to_phone'             => $to,
            'from_phone'           => $from,
            'body'                 => $body,
            'media_count'          => $numMedia,
            'media_urls'           => $mediaUrls ?: null,
            'status'               => 'received',
            'received_at'          => now(),
        ]);

        Log::info('Inbound SMS received', ['from' => $from, 'sid' => $sid, 'matched' => $entity ? 'yes' : 'no']);

        // Return empty TwiML
        return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200)
            ->header('Content-Type', 'text/xml');
    }

    /**
     * POST /webhooks/twilio/messages/status
     * Handles delivery status callbacks.
     */
    public function messageStatus(Request $request): Response
    {
        $payload = $request->all();

        if (config('twilio.log_raw_webhooks', true)) {
            TwilioWebhookLog::record('messages/status', $payload, true, $request->ip());
        }

        $sid = $payload['MessageSid'] ?? null;
        $status = strtolower($payload['MessageStatus'] ?? '');

        if (! $sid || ! $status) {
            return response('', 204);
        }

        $comm = Communication::where('provider_message_sid', $sid)->first();
        if (! $comm) {
            return response('', 204);
        }

        // Map Twilio status to internal status
        $update = ['status' => $status];
        if ($status === 'delivered') $update['delivered_at'] = now();
        if (in_array($status, ['failed', 'undelivered'])) {
            $update['failed_at'] = now();
            $update['error_code'] = $payload['ErrorCode'] ?? null;
            $update['error_message'] = $payload['ErrorMessage'] ?? null;
        }

        $comm->update($update);

        // Log event
        CommunicationEvent::create([
            'communication_id'  => $comm->id,
            'provider'          => 'twilio',
            'event_type'        => 'status_' . $status,
            'provider_sid'      => $sid,
            'payload'           => $payload,
            'processed_at'      => now(),
            'processing_status' => 'success',
        ]);

        return response('', 204);
    }

    // ── Consent Helpers ──────────────────────────────────

    private function handleOptOut(string $phone, string $body): void
    {
        ContactConsentLog::create([
            'phone_number'    => $phone,
            'normalized_phone' => $phone,
            'consent_status'  => 'opted_out',
            'source'          => 'inbound_keyword',
            'source_details'  => "Keyword: {$body}",
            'captured_at'     => now(),
        ]);
        Log::info('SMS opt-out received', ['phone' => $phone]);
    }

    private function handleOptIn(string $phone, string $body): void
    {
        // Revoke previous opt-out
        ContactConsentLog::where('normalized_phone', $phone)
            ->where('consent_status', 'opted_out')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        ContactConsentLog::create([
            'phone_number'    => $phone,
            'normalized_phone' => $phone,
            'consent_status'  => 'opted_in',
            'source'          => 'inbound_keyword',
            'source_details'  => "Keyword: {$body}",
            'captured_at'     => now(),
        ]);
        Log::info('SMS opt-in received', ['phone' => $phone]);
    }
}
