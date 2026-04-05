<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Objection extends Model
{
    protected $table = 'objection_library';

    protected $fillable = [
        'objection_text', 'category', 'rebuttal_level_1', 'rebuttal_level_2',
        'rebuttal_level_3', 'keywords', 'created_by', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }

    /**
     * Find objections matching keywords in text.
     */
    public static function detectFromText(string $text): \Illuminate\Support\Collection
    {
        $text = strtolower($text);
        return self::where('is_active', true)->get()->filter(function ($obj) use ($text) {
            $keywords = array_map('trim', explode(',', strtolower($obj->keywords ?? '')));
            foreach ($keywords as $kw) {
                if ($kw && str_contains($text, $kw)) return true;
            }
            return false;
        })->values();
    }

    public const CATEGORIES = [
        'money' => 'Money / Price',
        'timing' => 'Timing / Callback',
        'spouse' => 'Spouse / Partner',
        'trust' => 'Trust / Legitimacy',
        'card' => 'Card / Payment',
        'thinking' => 'Thinking / Deciding',
        'interest' => 'Not Interested',
        'competitor' => 'Tried Before / Competitor',
        'other' => 'Other',
    ];
}
