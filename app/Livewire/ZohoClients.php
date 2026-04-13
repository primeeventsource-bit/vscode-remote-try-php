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
            $rawHeaders = fgetcsv($handle);
            $headers = array_map('trim', $rawHeaders);

            // Normalize headers to lowercase for case-insensitive matching
            $headersLower = array_map('strtolower', $headers);

            // Map: our field => possible CSV header names (all lowercase for matching)
            $map = [
                'first_name'      => ['first name', 'first_name', 'firstname', 'given name', 'fname'],
                'last_name'       => ['last name', 'last_name', 'lastname', 'surname', 'family name', 'lname'],
                'email'           => ['email', 'email address', 'e-mail', 'email id', 'primary email'],
                'phone'           => ['phone', 'phone number', 'business phone', 'work phone', 'phone1', 'primary phone'],
                'mobile'          => ['mobile', 'mobile phone', 'cell phone', 'cell', 'mobile number'],
                'account_name'    => ['account name', 'company', 'company name', 'organization', 'account', 'business name'],
                'title'           => ['title', 'job title', 'designation', 'position'],
                'department'      => ['department', 'dept'],
                'mailing_address' => ['mailing street', 'mailing address', 'street', 'address', 'street address', 'mailing_street'],
                'mailing_city'    => ['mailing city', 'city', 'mailing_city'],
                'mailing_state'   => ['mailing state', 'state', 'state/province', 'mailing_state', 'province'],
                'mailing_zip'     => ['mailing zip', 'zip', 'zip code', 'postal code', 'mailing_zip', 'zipcode', 'pin code'],
                'mailing_country' => ['mailing country', 'country', 'mailing_country'],
                'lead_source'     => ['lead source', 'source', 'lead_source', 'campaign source'],
                'contact_owner'   => ['contact owner', 'owner', 'contact_owner', 'assigned to', 'record owner'],
                'zoho_id'         => ['contact id', 'record id', 'id', 'zoho id', 'record_id', 'contact_id', 'zoho_id'],
            ];

            // Build lookup: our_field => column_index (case-insensitive)
            $fieldIndex = [];
            foreach ($map as $field => $aliases) {
                foreach ($aliases as $alias) {
                    $key = array_search($alias, $headersLower);
                    if ($key !== false) {
                        $fieldIndex[$field] = $key;
                        break;
                    }
                }
            }

            // If no name fields matched, try to use first two columns as first/last name
            if (!isset($fieldIndex['first_name']) && !isset($fieldIndex['last_name'])) {
                // Check if there's a "Full Name" or "Name" column
                $nameIdx = array_search('full name', $headersLower);
                if ($nameIdx === false) $nameIdx = array_search('name', $headersLower);
                if ($nameIdx === false) $nameIdx = array_search('contact name', $headersLower);

                if ($nameIdx !== false) {
                    $fieldIndex['_full_name'] = $nameIdx; // special handling below
                } else {
                    // Last resort: use first column as name
                    $fieldIndex['first_name'] = 0;
                }
            }

            $created = 0;
            $updated = 0;
            $skipped = 0;
            $matchedCols = count($fieldIndex);

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 1) { $skipped++; continue; }

                $data = [];
                foreach ($fieldIndex as $field => $colIndex) {
                    if ($field === '_full_name') continue; // handled below
                    $data[$field] = isset($row[$colIndex]) ? trim($row[$colIndex]) : null;
                }

                // Handle full name split
                if (isset($fieldIndex['_full_name'])) {
                    $fullName = trim($row[$fieldIndex['_full_name']] ?? '');
                    $parts = preg_split('/\s+/', $fullName, 2);
                    $data['first_name'] = $parts[0] ?? '';
                    $data['last_name'] = $parts[1] ?? '';
                }

                // Skip only if we have absolutely nothing useful
                $hasAnyData = !empty($data['first_name']) || !empty($data['last_name']) || !empty($data['email']) || !empty($data['phone']) || !empty($data['account_name']);
                if (!$hasAnyData) {
                    $skipped++;
                    continue;
                }

                // Build zoho_id for dedup
                $zohoId = !empty($data['zoho_id'])
                    ? $data['zoho_id']
                    : 'csv_' . md5(($data['email'] ?? '') . ($data['first_name'] ?? '') . ($data['last_name'] ?? '') . ($data['phone'] ?? ''));

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

            $matchedNames = array_keys($fieldIndex);
            $matchedStr = implode(', ', array_filter($matchedNames, fn($n) => $n !== '_full_name'));
            $this->importResult = "Import complete — {$created} created, {$updated} updated, {$skipped} skipped. Matched {$matchedCols} columns: {$matchedStr}. CSV headers: " . implode(', ', array_slice($headers, 0, 8)) . (count($headers) > 8 ? '...' : '');
            $this->importType = $created > 0 || $updated > 0 ? 'success' : 'error';
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
