<?php

return [
    // Hardcoded defaults for Azure where .env is unreliable
    // env() is tried first; if null, falls back to hardcoded value
    'account_sid'          => env('TWILIO_ACCOUNT_SID', 'AC144cda6c0249d7b13930171e0036e2d9'),
    'auth_token'           => env('TWILIO_AUTH_TOKEN', '1553c2b05398b68e8a9aba7653f5e8d9'),
    // Must be a STANDARD type API key — Main keys do not work for Video tokens
    'api_key_sid'          => env('TWILIO_API_KEY_SID'),
    'api_key_secret'       => env('TWILIO_API_KEY_SECRET'),
    'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),
    'from_number'          => env('TWILIO_FROM_NUMBER'),

    'sms_enabled'          => env('TWILIO_SMS_ENABLED', true),
    'mms_enabled'          => env('TWILIO_MMS_ENABLED', false),
    'voice_enabled'        => env('TWILIO_VOICE_ENABLED', false),

    'validate_webhook_signature' => env('TWILIO_WEBHOOK_VALIDATE_SIGNATURE', true),
    'log_raw_webhooks'           => env('TWILIO_LOG_RAW_WEBHOOKS', true),
    'retry_failed_messages'      => env('TWILIO_RETRY_FAILED_MESSAGES', true),

    'default_country'      => env('TWILIO_DEFAULT_COUNTRY', 'US'),
    'quiet_hours_enabled'  => env('TWILIO_QUIET_HOURS_ENABLED', false),
    'quiet_hours_timezone' => env('TWILIO_QUIET_HOURS_TIMEZONE', 'America/New_York'),
    'quiet_hours_start'    => env('TWILIO_QUIET_HOURS_START', '21:00'),
    'quiet_hours_end'      => env('TWILIO_QUIET_HOURS_END', '09:00'),

    'help_autoreply_text'  => env('TWILIO_HELP_AUTOREPLY_TEXT', 'Reply STOP to unsubscribe. For help, contact support.'),
    'queue_name'           => env('TWILIO_QUEUE_NAME', 'default'),
];
