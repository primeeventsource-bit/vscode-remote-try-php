<?php

namespace App\Livewire;

use App\Models\ZohoClient;
use App\Models\ZohoToken;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Zoho Clients')]
class ZohoClients extends Component
{
    use WithFileUploads, WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';
    public int $perPage = 25;

    // Sync
    public string $syncMessage = '';
    public string $syncType = 'info';

    // CSV Import
    public $csvFile = null;
    public bool $showImportModal = false;
    public bool $importing = false;
    public ?string $importResult = null;
    public string $importType = 'info';
    public array $importPreview = [];
    public int $importTotal = 0;

    public function updatedSearch() { $this->resetPage(); }
    public function updatedStatusFilter() { $this->resetPage(); }

    public function openImport(): void
    {
        $this->showImportModal = true;
        $this->importResult = null;
        $this->importPreview = [];
        $this->csvFile = null;
        $this->importTotal = 0;
    }

    public function closeImport(): void
    {
        $this->showImportModal = false;
        $this->csvFile = null;
        $this->importPreview = [];
        $this->importResult = null;
    }

    public function updatedCsvFile(): void
    {
        $this->validate(['csvFile' => 'required|file|mimes:csv,txt|max:20480']);

        try {
            $path = $this->csvFile->getRealPath();
            $handle = fopen($path, 'r');
            $headers = fgetcsv($handle);
            $rows = [];
            $count = 0;
            while (($row = fgetcsv($handle)) !== false) {
                $count++;
                if ($count <= 5 && count($row) === count($headers)) {
                    $rows[] = array_combine(
                        array_map('trim', $headers),
                        array_map('trim', $row)
                    );
                }
            }
            fclose($handle);

            $this->importPreview = $rows;
            $this->importTotal = $count;
        } catch (\Throwable $e) {
            $this->importResult = 'Error reading file: ' . $e->getMessage();
            $this->importType = 'error';
        }
    }

    public function runImport(): void
    {
        $this->validate(['csvFile' => 'required|file|mimes:csv,txt|max:20480']);
        $this->importing = true;

        try {
            $path = $this->csvFile->getRealPath();
            $handle = fopen($path, 'r');
            $headers = array_map('trim', fgetcsv($handle));

            $map = [
                'first_name' => ['First Name', 'First name', 'first_name'],
                'last_name' => ['Last Name', 'Last name', 'last_name'],
                'email' => ['Email', 'Email Address', 'email'],
                'phone' => ['Phone', 'Phone Number', 'Business Phone', 'phone'],
                'mobile' => ['Mobile', 'Mobile Phone', 'Cell Phone', 'mobile'],
                'account_name' => ['Account Name', 'Company', 'account_name'],
                'title' => ['Title', 'Job Title', 'title'],
                'department' => ['Department', 'department'],
                'mailing_address' => ['Mailing Street', 'Mailing Address', 'Street', 'mailing_street'],
                'mailing_city' => ['Mailing City', 'City', 'mailing_city'],
                'mailing_state' => ['Mailing State', 'State', 'mailing_state'],
                'mailing_zip' => ['Mailing Zip', 'Zip', 'Zip Code', 'Postal Code', 'mailing_zip'],
                'mailing_country' => ['Mailing Country', 'Country', 'mailing_country'],
                'lead_source' => ['Lead Source', 'Source', 'lead_source'],
                'contact_owner' => ['Contact Owner', 'Owner', 'contact_owner'],
                'zoho_id' => ['Contact ID', 'Record ID', 'id', 'ID'],
            ];

            $fieldIndex = [];
            foreach ($map as $field => $aliases) {
                foreach ($aliases as $alias) {
                    $key = array_search($alias, $headers);
                    if ($key !== false) {
                        $fieldIndex[$field] = $key;
                        break;
                    }
                }
            }

            $created = 0;
            $updated = 0;
            $skipped = 0;

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 2) { $skipped++; continue; }

                $data = [];
                foreach ($fieldIndex as $field => $colIndex) {
                    $data[$field] = isset($row[$colIndex]) ? trim($row[$colIndex]) : null;
                }

                if (empty($data['first_name']) && empty($data['last_name'])) {
                    $skipped++;
                    continue;
                }

                $zohoId = !empty($data['zoho_id'])
                    ? $data['zoho_id']
                    : 'csv_' . md5(($data['email'] ?? '') . ($data['first_name'] ?? '') . ($data['last_name'] ?? ''));

                unset($data['zoho_id']);

                $existing = ZohoClient::where('zoho_id', $zohoId)->first();

                if ($existing) {
                    $existing->update(array_merge($data, ['last_synced_at' => now()]));
                    $updated++;
                } else {
                    ZohoClient::create(array_merge($data, [
                        'zoho_id' => $zohoId,
                        'last_synced_at' => now(),
                    ]));
                    $created++;
                }
            }

            fclose($handle);

            $this->importing = false;
            $this->showImportModal = false;
            $this->importResult = "Import complete — {$created} created, {$updated} updated, {$skipped} skipped.";
            $this->importType = 'success';
            $this->csvFile = null;
            $this->importPreview = [];
        } catch (\Throwable $e) {
            $this->importing = false;
            $this->importResult = 'Import failed: ' . $e->getMessage();
            $this->importType = 'error';
            report($e);
        }
    }

    public function render()
    {
        $clients = collect();
        $isConnected = false;
        $totalClients = 0;

        try {
            $query = ZohoClient::query()->orderBy('last_name');

            if ($this->search) {
                $query->search($this->search);
            }
            if ($this->statusFilter !== 'all') {
                $query->where('status', $this->statusFilter);
            }

            $clients = $query->paginate($this->perPage);
            $totalClients = ZohoClient::count();
            $isConnected = ZohoToken::where('expires_at', '>', now())->exists();
        } catch (\Throwable $e) {
            report($e);
        }

        return view('livewire.zoho-clients', compact('clients', 'isConnected', 'totalClients'));
    }
}
