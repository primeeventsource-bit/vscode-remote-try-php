<?php

namespace App\Livewire;

use App\Models\CrmSheet;
use App\Models\CrmSheetPermission;
use App\Models\CrmFileActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Spreadsheets')]
class Spreadsheets extends Component
{
    use WithFileUploads, WithPagination;

    public string $search = '';
    public string $tab = 'all';
    public int $perPage = 25;

    public function updatedSearch() { $this->resetPage(); }
    public function updatedTab() { $this->resetPage(); }
    public function updatedPerPage() { $this->resetPage(); }
    public ?int $editingId = null;
    public string $editTitle = '';
    public array $editData = [];
    public bool $showShareModal = false;
    public ?int $sharingSheetId = null;
    public array $shareUserIds = [];
    public string $sharePermission = 'view';
    public $csvUpload = null;

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
        return $user && ($user->hasRole('master_admin', 'admin') || $user->hasPerm('create_spreadsheets'));
    }

    public function mount(): void
    {
        $enabled = $this->moduleEnabled('spreadsheets.module_enabled');
        if (!$enabled || !auth()->user()?->hasPerm('view_spreadsheets')) {
            $this->redirectRoute('dashboard');
            session()->flash('error', 'Spreadsheets module is disabled or you do not have access.');
        }
    }

    public function createSheet(): void
    {
        if (!$this->canEdit()) return;
        try {
            $sheet = CrmSheet::create([
                'title' => 'Untitled Sheet',
                'data' => [['', '', '', '', '']],
                'owner_id' => auth()->id(),
            ]);
            CrmFileActivityLog::log('sheets', $sheet->id, 'created');
            $this->openSheet($sheet->id);
        } catch (\Throwable $e) {
            Log::error('Sheet create failed', ['error' => $e->getMessage()]);
        }
    }

    public function openSheet(int $id): void
    {
        $sheet = CrmSheet::find($id);
        if (!$sheet || !$sheet->userCan(auth()->user(), 'view')) return;
        $this->editingId = $sheet->id;
        $this->editTitle = $sheet->title;
        $this->editData = is_array($sheet->data) ? $sheet->data : [];
        CrmFileActivityLog::log('sheets', $id, 'opened');
    }

    public function saveSheet(): void
    {
        if (!$this->editingId) return;
        $sheet = CrmSheet::find($this->editingId);
        if (!$sheet || !$sheet->userCan(auth()->user(), 'edit')) return;
        $sheet->update(['title' => trim($this->editTitle) ?: 'Untitled Sheet', 'data' => $this->editData]);
        CrmFileActivityLog::log('sheets', $sheet->id, 'saved');
    }

    public function closeEditor(): void
    {
        if ($this->editingId && $this->canEdit()) $this->saveSheet();
        $this->editingId = null;
        $this->editTitle = '';
        $this->editData = [];
    }

    public function addRow(): void
    {
        if (!$this->canEdit() || !$this->editingId) return;
        $colCount = !empty($this->editData) ? count($this->editData[0] ?? []) : 5;
        $this->editData[] = array_fill(0, $colCount, '');
    }

    public function addColumn(): void
    {
        if (!$this->canEdit() || !$this->editingId) return;
        foreach ($this->editData as &$row) { $row[] = ''; }
    }

    public function deleteRow(int $index): void
    {
        if (!$this->canEdit()) return;
        unset($this->editData[$index]);
        $this->editData = array_values($this->editData);
    }

    public function deleteSheet(int $id): void
    {
        $sheet = CrmSheet::find($id);
        if (!$sheet || !$sheet->userCan(auth()->user(), 'delete')) return;
        if ($sheet->stored_path) Storage::disk('public')->delete($sheet->stored_path);
        CrmFileActivityLog::log('sheets', $id, 'deleted', ['title' => $sheet->title]);
        $sheet->delete();
        if ($this->editingId === $id) $this->editingId = null;
    }

    public function importFile(): void
    {
        if (!$this->canEdit()) return;
        $this->validate(['csvUpload' => 'required|file|max:25600|mimes:csv,txt,xlsx,xls']);

        try {
            $file = $this->csvUpload;
            $ext = strtolower($file->getClientOriginalExtension());
            $data = [];

            if (in_array($ext, ['xlsx', 'xls'])) {
                $data = $this->parseXlsx($file->getRealPath());
            } else {
                $content = file_get_contents($file->getRealPath());
                $lines = preg_split('/\r\n|\r|\n/', trim($content));
                foreach ($lines as $line) {
                    $data[] = array_map('trim', str_getcsv($line));
                }
            }

            if (empty($data)) {
                $data = [['', '', '', '', '']];
            }

            $sheet = CrmSheet::create([
                'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'data' => $data,
                'owner_id' => auth()->id(),
                'is_uploaded' => true,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);

            CrmFileActivityLog::log('sheets', $sheet->id, 'imported', [
                'filename' => $sheet->original_filename,
                'rows' => count($data),
            ]);

            $this->csvUpload = null;
            $this->openSheet($sheet->id);
        } catch (\Throwable $e) {
            Log::error('Sheet import failed', ['error' => $e->getMessage()]);
            session()->flash('error', 'Import failed: ' . $e->getMessage());
        }
    }

    private function parseXlsx(string $path): array
    {
        $data = [];
        $zip = new \ZipArchive();

        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Cannot open XLSX file.');
        }

        // Read shared strings
        $strings = [];
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStringsXml) {
            $xml = simplexml_load_string($sharedStringsXml);
            foreach ($xml->si as $si) {
                $strings[] = (string) ($si->t ?? $si->r->t ?? '');
            }
        }

        // Read first worksheet
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if (!$sheetXml) {
            $zip->close();
            return $data;
        }

        $xml = simplexml_load_string($sheetXml);
        foreach ($xml->sheetData->row as $row) {
            $rowData = [];
            $maxCol = 0;

            foreach ($row->c as $cell) {
                $colRef = preg_replace('/\d+/', '', (string) $cell['r']);
                $colIndex = $this->colLetterToIndex($colRef);
                $maxCol = max($maxCol, $colIndex);

                $value = '';
                if ((string) $cell['t'] === 's' && isset($strings[(int) $cell->v])) {
                    $value = $strings[(int) $cell->v];
                } elseif (isset($cell->v)) {
                    $value = (string) $cell->v;
                }

                // Fill gaps
                while (count($rowData) < $colIndex) {
                    $rowData[] = '';
                }
                $rowData[$colIndex] = $value;
            }

            $data[] = $rowData;
        }

        $zip->close();

        // Normalize column count
        $maxCols = max(1, ...array_map('count', $data ?: [[]]));
        foreach ($data as &$row) {
            while (count($row) < $maxCols) {
                $row[] = '';
            }
        }

        return $data;
    }

    private function colLetterToIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
        }
        return $index - 1;
    }

    public function openShareModal(int $id): void
    {
        if (!$this->canEdit()) return;
        $sheet = CrmSheet::find($id);
        if (!$sheet || !$sheet->userCan(auth()->user(), 'share')) return;
        $this->sharingSheetId = $id;
        $this->shareUserIds = $sheet->permissions()->pluck('user_id')->map(fn($id) => (string) $id)->toArray();
        $this->sharePermission = 'view';
        $this->showShareModal = true;
    }

    public function saveSharing(): void
    {
        if (!$this->sharingSheetId || !$this->canEdit()) return;
        $sheet = CrmSheet::find($this->sharingSheetId);
        if (!$sheet) return;
        $sheet->permissions()->delete();
        foreach (array_map('intval', $this->shareUserIds) as $uid) {
            if ($uid === auth()->id()) continue;
            $user = User::find($uid);
            CrmSheetPermission::create([
                'sheet_id' => $sheet->id, 'user_id' => $uid,
                'permission_type' => ($user && $user->hasRole('agent')) ? 'view' : $this->sharePermission,
                'granted_by' => auth()->id(), 'created_at' => now(),
            ]);
        }
        CrmFileActivityLog::log('sheets', $sheet->id, 'shared');
        $this->showShareModal = false;
        $this->sharingSheetId = null;
    }

    public function render()
    {
        $user = auth()->user();
        $query = CrmSheet::accessibleBy($user)->orderByDesc('updated_at');
        if ($this->search) $query->where('title', 'like', '%' . $this->search . '%');
        if ($this->tab === 'my') $query->where('owner_id', $user->id);
        elseif ($this->tab === 'shared') $query->whereHas('permissions', fn($q) => $q->where('user_id', $user->id));
        elseif ($this->tab === 'recent') $query->where('updated_at', '>=', now()->subDays(7));
        $sheets = $query->paginate($this->perPage);
        $users = User::all();
        $canEdit = $this->canEdit();
        return view('livewire.spreadsheets', compact('sheets', 'users', 'canEdit'));
    }
}
