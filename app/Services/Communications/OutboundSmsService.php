<?php

namespace App\Services\Communications;

use App\Models\Communication;
use App\Models\CommunicationThread;
use App\Models\ContactConsentLog;
use App\Models\User;
use App\Support\Phone\PhoneNumberNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Central outbound SMS service. Creates communication record + dispatches send job.
 * Never sends directly — always queued.
 */
class OutboundSmsService
{
    /**
     * Queue an outbound SMS.
     *
     * @param Model $entity Lead, Deal, or Client
     * @param string $toPhone Raw or normalized phone
     * @param string $body Message text
     * @param User $sender CRM user sending the message
     */
    public static function queue(Model $entity, string $toPhone, string $body, User $sender): array
    {
        // Normalize phone
        $normalized = PhoneNumberNormalizer::normalize($toPhone, config('twilio.default_country', 'US'));
        if (! $normalized) {
            return ['success' => false, 'error' => 'Invalid phone number: cannot normalize to E.164'];
        }

        // Check consent
        if (ContactConsentLog::isOptedOut($normalized)) {
            return ['success' => false, 'error' => 'Cannot send: this number has opted out of messages'];
        }

        // Check quiet hours
        if (self::isQuietHours()) {
            return ['success' => false, 'error' => 'Cannot send during quiet hours (' . config('twilio.quiet_hours_start') . ' - ' . config('twilio.quiet_hours_end') . ')'];
        }

        // Find or create thread
        $thread = CommunicationThread::firstOrCreate(
            [
                'threadable_type' => get_class($entity),
                'threadable_id'   => $entity->getKey(),
                'channel'         => 'sms',
                'phone_number'    => $normalized,
            ],
            [
                'status'     => 'open',
                'created_by' => $sender->id,
            ]
        );

        // Create communication record in "queued" status
        $comm = Communication::create([
            'thread_id'         => $thread->id,
            'communicable_type' => get_class($entity),
            'communicable_id'   => $entity->getKey(),
            'user_id'           => $sender->id,
            'provider'          => 'twilio',
            'channel'           => 'sms',
            'direction'         => 'outbound',
            'message_type'      => 'text',
            'to_phone'          => $normalized,
            'from_phone'        => config('twilio.from_number', ''),
            'body'              => $body,
            'status'            => 'queued',
            'created_by'        => $sender->id,
        ]);

        // Dispatch queued job
        \App\Jobs\Communications\SendOutboundSmsJob::dispatch($comm->id);

        // Update thread timestamps
        $thread->update([
            'last_message_at'  => now(),
            'last_outbound_at' => now(),
        ]);

        return ['success' => true, 'communication_id' => $comm->id];
    }

    private static function isQuietHours(): bool
    {
        if (! config('twilio.quiet_hours_enabled', false)) return false;

        $tz = config('twilio.quiet_hours_timezone', 'America/New_York');
        $now = now()->timezone($tz);
        $start = $now->copy()->setTimeFromTimeString(config('twilio.quiet_hours_start', '21:00'));
        $end = $now->copy()->setTimeFromTimeString(config('twilio.quiet_hours_end', '09:00'));

        if ($start->greaterThan($end)) {
            // Overnight quiet hours (e.g., 21:00 - 09:00)
            return $now->greaterThanOrEqualTo($start) || $now->lessThan($end);
        }

        return $now->between($start, $end);
    }
}
