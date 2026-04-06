<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SalesScript extends Model
{
    protected $fillable = [
        'name', 'slug', 'category', 'stage', 'content',
        'content_hash', 'character_count', 'version_number',
        'source_type', 'source_filename',
        'is_active', 'is_default', 'order_index',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'is_default' => 'boolean',
    ];

    // ── Column check cache ──────────────────────────────────

    private static ?bool $hasDefaultCol = null;
    private static ?bool $hasHashCol = null;

    public static function hasDefaultColumn(): bool
    {
        if (self::$hasDefaultCol === null) {
            try { self::$hasDefaultCol = Schema::hasColumn('sales_scripts', 'is_default'); } catch (\Throwable $e) { self::$hasDefaultCol = false; }
        }
        return self::$hasDefaultCol;
    }

    public static function hasHashColumn(): bool
    {
        if (self::$hasHashCol === null) {
            try { self::$hasHashCol = Schema::hasColumn('sales_scripts', 'content_hash'); } catch (\Throwable $e) { self::$hasHashCol = false; }
        }
        return self::$hasHashCol;
    }

    // ── Relationships ───────────────────────────────────────

    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function updatedBy() { return $this->belongsTo(User::class, 'updated_by'); }

    public function versions()
    {
        return $this->hasMany(ScriptVersion::class, 'script_id')->orderByDesc('version_number');
    }

    // ── Scopes ──────────────────────────────────────────────

    public function scopeActive($q) { return $q->where('is_active', true); }
    public function scopeByStage($q, string $stage) { return $q->where('stage', $stage); }
    public function scopeByCategory($q, string $cat) { return $q->where('category', $cat); }

    public function scopeDefaults($q)
    {
        return self::hasDefaultColumn() ? $q->where('is_default', true) : $q;
    }

    public function scopeOrderByDefault($q)
    {
        return self::hasDefaultColumn() ? $q->orderByDesc('is_default') : $q;
    }

    // ── Helpers ─────────────────────────────────────────────

    public static function defaultForStage(string $stage): ?self
    {
        if (self::hasDefaultColumn()) {
            $default = static::active()->byStage($stage)->where('is_default', true)->first();
            if ($default) return $default;
        }
        return static::active()->byStage($stage)->orderBy('order_index')->first();
    }

    public function makeDefault(): void
    {
        if (! self::hasDefaultColumn()) return;
        static::where('stage', $this->stage)->where('id', '!=', $this->id)->update(['is_default' => false]);
        $this->update(['is_default' => true, 'is_active' => true]);
    }

    // ── Integrity ───────────────────────────────────────────

    public function computeContentHash(): string
    {
        return hash('sha256', $this->content ?? '');
    }

    public function computeCharacterCount(): int
    {
        return mb_strlen($this->content ?? '');
    }

    public function updateIntegrity(): void
    {
        if (! self::hasHashColumn()) return;
        $this->content_hash = $this->computeContentHash();
        $this->character_count = $this->computeCharacterCount();
        $this->saveQuietly();
    }

    public function integrityPasses(): bool
    {
        if (! self::hasHashColumn() || ! $this->content_hash) return true;
        return $this->content_hash === $this->computeContentHash();
    }

    // ── Versioning ──────────────────────────────────────────

    public function createVersionSnapshot(?int $userId = null, ?string $sourceType = null, ?string $sourceFilename = null): ScriptVersion
    {
        try {
            return ScriptVersion::create([
                'script_id'       => $this->id,
                'version_number'  => $this->version_number ?? 1,
                'title_snapshot'  => $this->name,
                'body_snapshot'   => $this->content,
                'content_hash'    => $this->computeContentHash(),
                'character_count' => $this->computeCharacterCount(),
                'source_type'     => $sourceType ?? $this->source_type,
                'source_filename' => $sourceFilename ?? $this->source_filename,
                'edited_by'       => $userId ?? auth()->id(),
                'created_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            // script_versions table may not exist yet
            return new ScriptVersion();
        }
    }

    /**
     * Save new content with versioning + integrity + audit.
     */
    public function updateContent(string $newContent, ?int $userId = null, ?string $sourceType = 'manual'): self
    {
        $oldContent = $this->content;
        $oldHash = $this->content_hash;

        // Create version snapshot of CURRENT content before overwriting
        if ($oldContent) {
            $this->createVersionSnapshot($userId, $this->source_type, $this->source_filename);
        }

        // Update the content
        $this->content = $newContent;
        $this->updated_by = $userId ?? auth()->id();
        $this->source_type = $sourceType;

        if (self::hasHashColumn()) {
            $this->content_hash = hash('sha256', $newContent);
            $this->character_count = mb_strlen($newContent);
            $this->version_number = ($this->version_number ?? 0) + 1;
        }

        $this->save();

        // Verify integrity after save
        $this->refresh();
        if (self::hasHashColumn() && $this->content_hash !== hash('sha256', $newContent)) {
            \Log::error('Script integrity check FAILED after save', [
                'script_id' => $this->id,
                'expected_hash' => hash('sha256', $newContent),
                'actual_hash' => $this->content_hash,
            ]);
        }

        // Audit log
        try {
            AuditLog::record('script.updated', $this, [
                'content_hash' => $oldHash,
                'character_count' => mb_strlen($oldContent ?? ''),
            ], [
                'content_hash' => $this->content_hash,
                'character_count' => $this->character_count,
            ]);
        } catch (\Throwable $e) {}

        return $this;
    }

    // ── Safe accessor ───────────────────────────────────────

    public function getIsDefaultAttribute($value): bool
    {
        if (! self::hasDefaultColumn()) return false;
        return (bool) $value;
    }
}
