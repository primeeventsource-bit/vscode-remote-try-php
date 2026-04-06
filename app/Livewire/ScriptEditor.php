<?php

namespace App\Livewire;

use App\Models\AuditLog;
use App\Models\SalesScript;
use App\Models\ScriptVersion;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Script Editor')]
class ScriptEditor extends Component
{
    // ── State ────────────────────────────────────────────────
    public ?int $scriptId = null;
    public string $name = '';
    public string $slug = '';
    public string $category = 'closer';
    public string $stage = 'closer';
    public string $body = '';
    public bool $isActive = true;
    public bool $isDefault = false;

    // UI state
    public string $tab = 'editor';      // editor | preview | versions | raw
    public string $flashMsg = '';
    public string $flashType = 'success';
    public bool $showCreate = false;
    public ?int $selectedVersionId = null;

    // ── Mount ────────────────────────────────────────────────

    public function mount(?int $id = null): void
    {
        if ($id) {
            $script = SalesScript::find($id);
            if ($script) {
                $this->loadScript($script);
            }
        }
    }

    private function loadScript(SalesScript $script): void
    {
        $this->scriptId   = $script->id;
        $this->name       = $script->name;
        $this->slug       = $script->slug;
        $this->category   = $script->category;
        $this->stage      = $script->stage ?? 'closer';
        $this->body       = $script->content ?? '';
        $this->isActive   = $script->is_active;
        $this->isDefault  = $script->is_default;
    }

    // ── Actions ──────────────────────────────────────────────

    public function selectScript(int $id): void
    {
        $script = SalesScript::find($id);
        if ($script) {
            $this->loadScript($script);
            $this->tab = 'editor';
            $this->selectedVersionId = null;
        }
    }

    public function saveScript(): void
    {
        $user = auth()->user();
        if (! $user?->hasRole('master_admin', 'admin')) {
            $this->flash('Unauthorized', 'error');
            return;
        }

        $this->validate([
            'name'     => 'required|string|max:255',
            'category' => 'required|string|in:fronter,closer,verification,voicemail,bridge,closing',
            'stage'    => 'required|string|in:fronter,closer,verification',
            'body'     => 'required|string|min:10',
        ]);

        if (! $this->slug) {
            $this->slug = Str::slug($this->name);
        }

        if ($this->scriptId) {
            // UPDATE existing
            $script = SalesScript::find($this->scriptId);
            if (! $script) {
                $this->flash('Script not found', 'error');
                return;
            }

            // Use updateContent which handles versioning + integrity + audit
            $script->name = $this->name;
            $script->slug = $this->slug;
            $script->category = $this->category;
            $script->stage = $this->stage;
            $script->is_active = $this->isActive;
            $script->save();

            // Content update with versioning
            if ($script->content !== $this->body) {
                $script->updateContent($this->body, $user->id, 'manual');
            }

            if ($this->isDefault) {
                $script->makeDefault();
            }

            $this->loadScript($script->fresh());
            $this->flash('Script saved — ' . mb_strlen($this->body) . ' chars, integrity verified');
        } else {
            // CREATE new
            $exists = SalesScript::where('slug', $this->slug)->exists();
            if ($exists) {
                $this->slug .= '-' . time();
            }

            $data = [
                'name'       => $this->name,
                'slug'       => $this->slug,
                'category'   => $this->category,
                'stage'      => $this->stage,
                'content'    => $this->body,
                'is_active'  => $this->isActive,
                'order_index' => 0,
                'created_by' => $user->id,
            ];

            if (SalesScript::hasDefaultColumn()) {
                $data['is_default'] = $this->isDefault;
            }
            if (SalesScript::hasHashColumn()) {
                $data['content_hash'] = hash('sha256', $this->body);
                $data['character_count'] = mb_strlen($this->body);
                $data['version_number'] = 1;
                $data['source_type'] = 'manual';
            }

            $script = SalesScript::create($data);

            if ($this->isDefault && SalesScript::hasDefaultColumn()) {
                $script->makeDefault();
            }

            $script->createVersionSnapshot($user->id, 'manual');

            try { AuditLog::record('script.created', $script); } catch (\Throwable $e) {}

            $this->loadScript($script);
            $this->showCreate = false;
            $this->flash('Script created — ' . mb_strlen($this->body) . ' chars');
        }
    }

    public function duplicateScript(): void
    {
        if (! auth()->user()?->hasRole('master_admin', 'admin')) return;
        if (! $this->scriptId) return;

        $original = SalesScript::find($this->scriptId);
        if (! $original) return;

        $data = [
            'name'       => $original->name . ' (Copy)',
            'slug'       => $original->slug . '-copy-' . time(),
            'category'   => $original->category,
            'stage'      => $original->stage,
            'content'    => $original->content,
            'is_active'  => false,
            'order_index' => $original->order_index + 1,
            'created_by' => auth()->id(),
        ];
        if (SalesScript::hasDefaultColumn()) $data['is_default'] = false;
        if (SalesScript::hasHashColumn()) {
            $data['content_hash'] = hash('sha256', $original->content);
            $data['character_count'] = mb_strlen($original->content);
            $data['version_number'] = 1;
            $data['source_type'] = 'manual';
        }

        $copy = SalesScript::create($data);
        $this->loadScript($copy);
        $this->flash('Script duplicated');
    }

    public function restoreVersion(int $versionId): void
    {
        if (! auth()->user()?->hasRole('master_admin')) return;
        if (! $this->scriptId) return;

        try {
            $version = ScriptVersion::where('id', $versionId)->where('script_id', $this->scriptId)->first();
        } catch (\Throwable $e) {
            $this->flash('Version history not available yet', 'error');
            return;
        }

        if (! $version) {
            $this->flash('Version not found', 'error');
            return;
        }

        $script = SalesScript::find($this->scriptId);
        if (! $script) return;

        $script->updateContent($version->body_snapshot, auth()->id(), 'restore');
        $this->loadScript($script->fresh());
        $this->tab = 'editor';
        $this->flash('Restored to version ' . $version->version_number);
    }

    public function startCreate(): void
    {
        $this->scriptId = null;
        $this->name = '';
        $this->slug = '';
        $this->category = 'closer';
        $this->stage = 'closer';
        $this->body = '';
        $this->isActive = true;
        $this->isDefault = false;
        $this->showCreate = true;
        $this->tab = 'editor';
    }

    public function exportTxt(): string
    {
        return $this->body;
    }

    private function flash(string $msg, string $type = 'success'): void
    {
        $this->flashMsg = $msg;
        $this->flashType = $type;
    }

    // ── Render ───────────────────────────────────────────────

    public function render()
    {
        $user = auth()->user();
        $isAdmin = $user->hasRole('master_admin', 'admin');
        $isMaster = $user->hasRole('master_admin');

        $scripts = collect();
        try {
            $scripts = SalesScript::orderByDefault()->orderBy('stage')->orderBy('order_index')->get();
        } catch (\Throwable $e) {
            try { $scripts = SalesScript::orderBy('stage')->orderBy('order_index')->get(); } catch (\Throwable $e2) {}
        }

        $versions = collect();
        $currentScript = null;
        if ($this->scriptId) {
            $currentScript = SalesScript::find($this->scriptId);
            try { $versions = ScriptVersion::where('script_id', $this->scriptId)->orderByDesc('version_number')->limit(20)->get(); } catch (\Throwable $e) {}
        }

        $charCount = mb_strlen($this->body);
        $contentHash = $this->body ? substr(hash('sha256', $this->body), 0, 12) : '-';
        $integrityOk = $currentScript ? $currentScript->integrityPasses() : true;

        return view('livewire.script-editor', compact(
            'scripts', 'versions', 'currentScript',
            'isAdmin', 'isMaster', 'charCount', 'contentHash', 'integrityOk'
        ));
    }
}
