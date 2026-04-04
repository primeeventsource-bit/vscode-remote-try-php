<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A graceful encrypted cast that handles both plain-text (pre-migration)
 * and encrypted (post-migration) values without crashing.
 *
 * - On GET: tries decrypt(); if it fails, returns the raw value as-is
 * - On SET: always encrypts before writing
 *
 * This allows the app to function before and after the card-data migration runs.
 */
class SafeEncrypted implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        try {
            return decrypt($value);
        } catch (\Throwable $e) {
            // Value is still plain text (migration hasn't run yet) — return as-is
            return $value;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        return encrypt($value);
    }
}
