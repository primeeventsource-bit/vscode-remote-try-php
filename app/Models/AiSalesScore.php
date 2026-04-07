<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiSalesScore extends Model
{
    protected $fillable = [
        'entity_type', 'entity_id', 'score_type',
        'numeric_score', 'label', 'confidence_score',
        'reasons_json', 'risks_json', 'recommendations_json', 'calculated_at',
    ];

    protected $casts = [
        'reasons_json' => 'array',
        'risks_json' => 'array',
        'recommendations_json' => 'array',
        'calculated_at' => 'datetime',
    ];

    public static function forEntity(string $type, int $id, string $scoreType): ?self
    {
        return self::where('entity_type', $type)->where('entity_id', $id)->where('score_type', $scoreType)->first();
    }

    public static function upsertScore(string $entityType, int $entityId, string $scoreType, array $data): self
    {
        return self::updateOrCreate(
            ['entity_type' => $entityType, 'entity_id' => $entityId, 'score_type' => $scoreType],
            $data + ['calculated_at' => now()]
        );
    }
}
