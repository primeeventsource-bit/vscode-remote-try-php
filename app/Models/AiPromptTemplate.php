<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiPromptTemplate extends Model
{
    protected $fillable = ['name', 'slug', 'category', 'system_prompt', 'user_prompt_template', 'tone', 'stage', 'is_active', 'version', 'created_by'];
    protected $casts = ['is_active' => 'boolean'];

    public static function forCategory(string $category, ?string $tone = null): ?self
    {
        $q = self::where('category', $category)->where('is_active', true);
        if ($tone) $q->where('tone', $tone);
        return $q->first();
    }

    public function renderUserPrompt(array $vars): string
    {
        $text = $this->user_prompt_template;
        foreach ($vars as $k => $v) {
            $text = str_replace("{{{$k}}}", (string) $v, $text);
        }
        return $text;
    }
}
