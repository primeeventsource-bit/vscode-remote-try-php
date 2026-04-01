<?php

namespace App\Livewire;

use App\Models\CrmDocument;
use App\Models\CrmDocumentPermission;
use App\Models\CrmFileActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Documents')]
class Documents extends Component
{
    use WithFileUploads;

    public string $search = '';
    public string $tab = 'all'; // all, my, shared, recent
    public ?int $editingId = null;
    public string $editTitle = '';
    public string $editContent = '';
    public bool $showCreateModal = false;
    public bool $showShareModal = false;
    public ?int $sharingDocId = null;
    public array $shareUserIds = [];
    public string $sharePermission = 'view';
    public $fileUpload = null;

    private function moduleEnabled(string $key, bool $default = true): bool
    {
        try {
            $raw = DB::table('crm_settings')->where('key', $key)->value('value');
            return $raw === null ? $default : (bool) json_decode($raw, true);
        } catch (\Throwable $e) {
            return $default;
        }
    }

    private function canEdit(): bool
    {
        $user = auth()->user();
        return $user && ($user->hasRole('master_admin', 'admin') || $user->hasPerm('create_documents'));
    }

    public function mount(): void
    {
        $enabled = $this->moduleEnabled('documents.module_enabled');
        if (!$enabled || !auth()->user()?->hasPerm('view_documents')) {
            $this->redirectRoute('dashboard');
            session()->flash('error', 'Documents module is disabled or you do not have access.');
        }
    }

    public function createDocument(): void
    {
        if (!$this->canEdit()) return;

        try {
            $doc = CrmDocument::create([
                'title' => 'Untitled Document',
                'content' => '',
                'type' => 'rich_text',
                'owner_id' => auth()->id(),
            ]);
            CrmFileActivityLog::log('documents', $doc->id, 'created');
            $this->editingId = $doc->id;
            $this->editTitle = $doc->title;
            $this->editContent = '';
            $this->showCreateModal = false;
        } catch (\Throwable $e) {
            Log::error('Document create failed', ['error' => $e->getMessage()]);
        }
    }

    public function openDocument(int $id): void
    {
        $doc = CrmDocument::find($id);
        if (!$doc || !$doc->userCan(auth()->user(), 'view')) return;

        $this->editingId = $doc->id;
        $this->editTitle = $doc->title;
        $this->editContent = $doc->content ?? '';
        CrmFileActivityLog::log('documents', $id, 'opened');
    }

    public function saveDocument(): void
    {
        if (!$this->editingId) return;

        $doc = CrmDocument::find($this->editingId);
        if (!$doc || !$doc->userCan(auth()->user(), 'edit')) return;

        $doc->update([
            'title' => trim($this->editTitle) ?: 'Untitled Document',
            'content' => $this->editContent,
        ]);
        CrmFileActivityLog::log('documents', $doc->id, 'saved');
    }

    public function closeEditor(): void
    {
        if ($this->editingId && $this->canEdit()) {
            $this->saveDocument();
        }
        $this->editingId = null;
        $this->editTitle = '';
        $this->editContent = '';
    }

    public function deleteDocument(int $id): void
    {
        $doc = CrmDocument::find($id);
        if (!$doc || !$doc->userCan(auth()->user(), 'delete')) return;

        if ($doc->stored_path) {
            Storage::disk('public')->delete($doc->stored_path);
        }
        CrmFileActivityLog::log('documents', $id, 'deleted', ['title' => $doc->title]);
        $doc->delete();

        if ($this->editingId === $id) {
            $this->editingId = null;
        }
    }

    public function uploadDocument(): void
    {
        if (!$this->canEdit()) return;

        $this->validate(['fileUpload' => 'required|file|max:25600|mimes:pdf,doc,docx,txt,rtf']);

        try {
            $file = $this->fileUpload;
            $path = $file->store('crm-documents', 'public');

            $doc = CrmDocument::create([
                'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'type' => 'uploaded',
                'owner_id' => auth()->id(),
                'is_uploaded' => true,
                'original_filename' => $file->getClientOriginalName(),
                'stored_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);

            CrmFileActivityLog::log('documents', $doc->id, 'uploaded', [
                'filename' => $doc->original_filename,
                'size' => $doc->file_size,
            ]);

            $this->fileUpload = null;
        } catch (\Throwable $e) {
            Log::error('Document upload failed', ['error' => $e->getMessage()]);
        }
    }

    public function openShareModal(int $id): void
    {
        if (!$this->canEdit()) return;
        $doc = CrmDocument::find($id);
        if (!$doc || !$doc->userCan(auth()->user(), 'share')) return;

        $this->sharingDocId = $id;
        $this->shareUserIds = $doc->permissions()->pluck('user_id')->map(fn($id) => (string) $id)->toArray();
        $this->sharePermission = 'view';
        $this->showShareModal = true;
    }

    public function saveSharing(): void
    {
        if (!$this->sharingDocId || !$this->canEdit()) return;

        $doc = CrmDocument::find($this->sharingDocId);
        if (!$doc) return;

        // Remove old permissions and set new ones
        $doc->permissions()->delete();
        $userIds = array_map('intval', $this->shareUserIds);

        foreach ($userIds as $uid) {
            if ($uid === auth()->id()) continue;

            $user = User::find($uid);
            // Agents always get view-only
            $perm = ($user && $user->hasRole('agent')) ? 'view' : $this->sharePermission;

            CrmDocumentPermission::create([
                'document_id' => $doc->id,
                'user_id' => $uid,
                'permission_type' => $perm,
                'granted_by' => auth()->id(),
                'created_at' => now(),
            ]);
        }

        CrmFileActivityLog::log('documents', $doc->id, 'shared', ['users' => $userIds]);
        $this->showShareModal = false;
        $this->sharingDocId = null;
    }

    public function render()
    {
        $user = auth()->user();

        $query = CrmDocument::accessibleBy($user)->orderByDesc('updated_at');

        if ($this->search) {
            $query->where('title', 'like', '%' . $this->search . '%');
        }

        if ($this->tab === 'my') {
            $query->where('owner_id', $user->id);
        } elseif ($this->tab === 'shared') {
            $query->whereHas('permissions', fn($q) => $q->where('user_id', $user->id));
        } elseif ($this->tab === 'recent') {
            $query->where('updated_at', '>=', now()->subDays(7));
        }

        $documents = $query->limit(100)->get();
        $users = User::all();
        $canEdit = $this->canEdit();

        return view('livewire.documents', compact('documents', 'users', 'canEdit'));
    }
}
