<?php

namespace App\Jobs\Communications;

use App\Models\Communication;
use App\Models\CommunicationEvent;
use App\Services\Twilio\TwilioSmsSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendOutboundSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 30;

    public function __construct(public int $communicationId) {}

    public function handle(): void
    {
        $comm = Communication::find($this->communicationId);
        if (! $comm) return;

        // Idempotency: don't re-send if already has a provider SID
        if ($comm->provider_message_sid) {
            Log::info('SMS already sent, skipping', ['id' => $comm->id, 'sid' => $comm->provider_message_sid]);
            return;
        }

        $comm->update(['status' => 'sending']);

        $statusCallbackUrl = config('app.url') . '/webhooks/twilio/messages/status';

        $result = TwilioSmsSender::send(
            to: $comm->to_phone,
            body: $comm->body,
            from: $comm->from_phone ?: null,
            statusCallbackUrl: $statusCallbackUrl,
        );

        if ($result['success']) {
            $comm->update([
                'provider_message_sid' => $result['sid'],
                'status'  => $result['status'] ?? 'sent',
                'sent_at' => now(),
            ]);

            CommunicationEvent::create([
                'communication_id'  => $comm->id,
                'provider'          => 'twilio',
                'event_type'        => 'sent',
                'provider_sid'      => $result['sid'],
                'payload'           => $result,
                'processed_at'      => now(),
                'processing_status' => 'success',
            ]);
        } else {
            $comm->update([
                'status'        => 'failed',
                'error_message' => $result['error'],
                'failed_at'     => now(),
            ]);

            CommunicationEvent::create([
                'communication_id'  => $comm->id,
                'provider'          => 'twilio',
                'event_type'        => 'send_failed',
                'payload'           => $result,
                'processed_at'      => now(),
                'processing_status' => 'failed',
                'error_message'     => $result['error'],
            ]);

            Log::error('Outbound SMS failed', ['id' => $comm->id, 'to' => $comm->to_phone, 'error' => $result['error']]);
        }
    }

    public function failed(\Throwable $e): void
    {
        $comm = Communication::find($this->communicationId);
        if ($comm) {
            $comm->update(['status' => 'failed', 'error_message' => 'Job failed: ' . $e->getMessage(), 'failed_at' => now()]);
        }
        Log::error('SendOutboundSmsJob failed permanently', ['id' => $this->communicationId, 'error' => $e->getMessage()]);
    }
}
