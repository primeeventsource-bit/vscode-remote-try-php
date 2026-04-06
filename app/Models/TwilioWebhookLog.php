<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TwilioWebhookLog extends Model
{
    protected $fillable = [
        'event_key', 'endpoint', 'request_method',
        'signature_valid', 'payload', 'processed',
        'processed_at', 'response_code', 'error_message', 'ip_address',
    ];

    protected $casts = [
        'signature_valid' => 'boolean',
        'payload'         => 'array',
        'processed'       => 'boolean',
        'processed_at'    => 'datetime',
    ];

    public function scopeUnprocessed($q) { return $q->where('processed', false); }

    public static function record(string $endpoint, array $payload, bool $signatureValid, ?string $ip = null): self
    {
        $eventKey = md5($endpoint . ':' . json_encode($payload));

        return static::create([
            'event_key'       => $eventKey,
            'endpoint'        => $endpoint,
            'request_method'  => 'POST',
            'signature_valid' => $signatureValid,
            'payload'         => $payload,
            'ip_address'      => $ip,
        ]);
    }
}
