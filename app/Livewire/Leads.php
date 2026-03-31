<?php

namespace App\Livewire;

use App\Models\Lead;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Leads')]
class Leads extends Component
{
    public string $search = '';
    public string $filter = 'all';
    public string $resortFilter = 'all';
    public ?int $selectedLead = null;
    public string $transferCloser = '';
    public string $callbackDateTime = '';
    public bool $showAddModal = false;
    public bool $showImportModal = false;
    public string $csvText = '';
    public array $newLead = ['resort' => '', 'owner_name' => '', 'phone1' => '', 'phone2' => '', 'city' => '', 'st' => '', 'zip' => '', 'resort_location' => ''];

    public function selectLead($id) { $this->selectedLead = $this->selectedLead === $id ? null : $id; }

    public function setDisposition($id, $dispo, $closerId = null, $callbackDate = null)
    {
        $lead = Lead::find($id);
        if (!$lead) return;
        $data = ['disposition' => $dispo];
        if ($dispo === 'Transferred to Closer' && $closerId) {
            $data['transferred_to'] = $closerId;
            $data['assigned_to'] = $closerId;
            if (!$lead->original_fronter) $data['original_fronter'] = $lead->assigned_to;
        }
        if ($dispo === 'Callback' && $callbackDate) $data['callback_date'] = $callbackDate;
        $lead->update($data);
        $this->selectedLead = null;
    }

    public function doCallback($id)
    {
        if (!$this->callbackDateTime) return;
        $this->setDisposition($id, 'Callback', null, \Carbon\Carbon::parse($this->callbackDateTime)->format('n/j/Y g:i A'));
        $this->callbackDateTime = '';
    }

    public function transferToCloser($id)
    {
        if (!$this->transferCloser) return;
        $this->setDisposition($id, 'Transferred to Closer', (int) $this->transferCloser);
        $this->transferCloser = '';
    }

    public function saveLead()
    {
        Lead::create($this->newLead + ['source' => 'manual']);
        $this->reset('newLead', 'showAddModal');
        $this->newLead = ['resort' => '', 'owner_name' => '', 'phone1' => '', 'phone2' => '', 'city' => '', 'st' => '', 'zip' => '', 'resort_location' => ''];
    }

    public function importCsv()
    {
        $lines = explode("\n", trim($this->csvText));
        for ($i = 1; $i < count($lines); $i++) {
            $v = array_map('trim', str_getcsv($lines[$i]));
            if (count($v) < 2) continue;
            Lead::create(['resort' => $v[0] ?? '', 'owner_name' => $v[1] ?? '', 'phone1' => $v[2] ?? '', 'phone2' => $v[3] ?? '', 'city' => $v[4] ?? '', 'st' => $v[5] ?? '', 'zip' => $v[6] ?? '', 'resort_location' => $v[7] ?? '', 'source' => 'csv']);
        }
        $this->csvText = '';
        $this->showImportModal = false;
    }

    public function render()
    {
        $user = auth()->user();
        $isAdmin = $user->hasPerm('view_all_leads');
        $query = Lead::query()->orderBy('id', 'desc');
        if (!$isAdmin) $query->where('assigned_to', $user->id);
        if ($this->search) {
            $s = $this->search;
            $query->where(fn($q) => $q->where('owner_name', 'like', "%$s%")->orWhere('resort', 'like', "%$s%")->orWhere('phone1', 'like', "%$s%"));
        }
        if ($this->resortFilter !== 'all') $query->where('resort', $this->resortFilter);
        if ($this->filter === 'undisposed') $query->whereNull('disposition');
        if ($this->filter === 'transferred') $query->where('disposition', 'like', 'Transferred%');
        $leads = $query->get();
        $resorts = Lead::distinct()->pluck('resort')->filter()->sort();
        $closers = User::where('role', 'closer')->get();
        $users = User::all();
        $active = $this->selectedLead ? Lead::find($this->selectedLead) : null;
        return view('livewire.leads', compact('leads', 'resorts', 'closers', 'users', 'active', 'isAdmin'));
    }
}
