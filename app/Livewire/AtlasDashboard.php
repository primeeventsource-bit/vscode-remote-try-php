<?php

namespace App\Livewire;

use App\Models\AtlasLead;
use App\Models\AtlasParseLog;
use App\Services\AtlasAIService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Atlas Global')]
class AtlasDashboard extends Component
{
    use WithFileUploads, WithPagination;

    public string $activeTab = 'dashboard';
    public string $searchQuery = '';
    public string $filterStatus = 'ALL';
    public string $countySearch = '';
    public ?int $selectedCountyIdx = null;

    // AI Text Parser
    public string $pasteText = '';
    public string $aiCounty = 'Orange';
    public string $aiState = 'FL';
    public bool $aiParsing = false;
    public ?array $aiResults = null;
    public string $aiError = '';
    public array $aiSelected = [];

    // PDF Upload
    public $pdfFiles = [];
    public string $pdfCounty = 'Orange';
    public string $pdfState = 'FL';
    public bool $pdfParsing = false;
    public ?array $pdfResults = null;
    public string $pdfError = '';
    public array $pdfSelected = [];

    // Phone Lookup
    public ?int $phoneLookupLeadId = null;
    public bool $phoneLooking = false;
    public ?array $phoneResult = null;
    public string $phoneError = '';

    // Manual Add Form
    public bool $showAddForm = false;
    public string $formGrantee = '';
    public string $formGrantor = '';
    public string $formDate = '';
    public string $formAddress = '';
    public string $formCounty = '';
    public string $formState = '';
    public string $formInstrument = '';
    public string $formDeedType = '';

    public string $successMessage = '';
    public string $errorMessage = '';

    // ─── AI Text Parse ───────────────────────────────
    public function parseText()
    {
        $this->aiError = '';
        $this->aiResults = null;
        $this->aiSelected = [];

        if (strlen(trim($this->pasteText)) < 20) {
            $this->aiError = 'Please paste at least a few lines of deed search results.';
            return;
        }

        $this->aiParsing = true;

        try {
            $service = app(AtlasAIService::class);
            $results = $service->parseText($this->pasteText, $this->aiCounty, $this->aiState);

            $this->aiResults = $results;
            $this->aiSelected = array_keys($results);

            AtlasParseLog::create([
                'user_id' => auth()->id(),
                'parse_type' => 'text',
                'county' => $this->aiCounty,
                'state' => $this->aiState,
                'leads_found' => count($results),
                'raw_input_preview' => substr($this->pasteText, 0, 5000),
            ]);
        } catch (\Throwable $e) {
            report($e);
            $this->aiError = 'AI parsing failed: ' . $e->getMessage();
        }

        $this->aiParsing = false;
    }

    public function importAiResults()
    {
        if (!$this->aiResults) return;

        $count = 0;
        foreach ($this->aiSelected as $idx) {
            if (!isset($this->aiResults[$idx])) continue;
            $r = $this->aiResults[$idx];

            AtlasLead::create([
                'grantee' => $r['grantee'] ?? 'Unknown',
                'grantor' => $r['grantor'] ?? 'Unknown',
                'county' => $this->aiCounty,
                'state' => $this->aiState,
                'deed_date' => !empty($r['date']) ? $r['date'] : null,
                'address' => $r['address'] ?? null,
                'instrument' => $r['instrument'] ?? null,
                'deed_type' => $r['type'] ?? null,
                'status' => 'new',
                'source' => 'ai-text',
                'created_by' => auth()->id(),
            ]);
            $count++;
        }

        $this->successMessage = "{$count} leads imported from AI text parse.";
        $this->aiResults = null;
        $this->aiSelected = [];
        $this->pasteText = '';
    }

    // ─── PDF Upload ──────────────────────────────────
    public function parsePDFs()
    {
        $this->pdfError = '';
        $this->pdfResults = null;
        $this->pdfSelected = [];

        $this->validate([
            'pdfFiles.*' => 'file|mimes:pdf|max:10240',
        ]);

        if (empty($this->pdfFiles)) {
            $this->pdfError = 'Please upload at least one PDF.';
            return;
        }

        $this->pdfParsing = true;
        $allResults = [];

        try {
            $service = app(AtlasAIService::class);

            foreach ($this->pdfFiles as $file) {
                $base64 = base64_encode(file_get_contents($file->getRealPath()));
                $results = $service->parsePDF($base64, $this->pdfCounty, $this->pdfState);

                foreach ($results as &$r) {
                    $r['_filename'] = $file->getClientOriginalName();
                }
                $allResults = array_merge($allResults, $results);
            }

            $this->pdfResults = $allResults;
            $this->pdfSelected = array_keys($allResults);

            AtlasParseLog::create([
                'user_id' => auth()->id(),
                'parse_type' => 'pdf',
                'county' => $this->pdfCounty,
                'state' => $this->pdfState,
                'leads_found' => count($allResults),
                'files_processed' => count($this->pdfFiles),
            ]);
        } catch (\Throwable $e) {
            report($e);
            $this->pdfError = 'PDF parsing failed: ' . $e->getMessage();
        }

        $this->pdfParsing = false;
    }

    public function importPdfResults()
    {
        if (!$this->pdfResults) return;

        $count = 0;
        foreach ($this->pdfSelected as $idx) {
            if (!isset($this->pdfResults[$idx])) continue;
            $r = $this->pdfResults[$idx];

            AtlasLead::create([
                'grantee' => $r['grantee'] ?? 'Unknown',
                'grantor' => $r['grantor'] ?? 'Unknown',
                'county' => $this->pdfCounty,
                'state' => $this->pdfState,
                'deed_date' => !empty($r['date']) ? $r['date'] : null,
                'address' => $r['address'] ?? null,
                'instrument' => $r['instrument'] ?? null,
                'deed_type' => $r['type'] ?? null,
                'status' => 'new',
                'source' => 'ai-pdf',
                'source_filename' => $r['_filename'] ?? null,
                'created_by' => auth()->id(),
            ]);
            $count++;
        }

        $this->successMessage = "{$count} leads imported from PDF upload.";
        $this->pdfResults = null;
        $this->pdfSelected = [];
        $this->pdfFiles = [];
    }

    // ─── Phone Lookup ────────────────────────────────
    public function lookupPhone(int $leadId)
    {
        $this->phoneError = '';
        $this->phoneResult = null;
        $this->phoneLookupLeadId = $leadId;
        $this->phoneLooking = true;

        try {
            $lead = AtlasLead::findOrFail($leadId);
            $service = app(AtlasAIService::class);
            $result = $service->lookupPhone($lead->grantee, $lead->county ?? '', $lead->state ?? '', $lead->address ?? '');

            $phones = $result['phones'] ?? [];
            $lead->update([
                'phone_1' => $phones[0] ?? null,
                'phone_2' => $phones[1] ?? null,
                'phone_3' => $phones[2] ?? null,
                'phone_confidence' => $result['confidence'] ?? 'none',
                'phone_sources' => $result['sources'] ?? [],
            ]);

            $this->phoneResult = $result;

            AtlasParseLog::create([
                'user_id' => auth()->id(),
                'parse_type' => 'phone',
                'county' => $lead->county,
                'state' => $lead->state,
                'leads_found' => count($phones),
            ]);

            $this->successMessage = count($phones) > 0
                ? count($phones) . ' phone(s) found for ' . $lead->grantee
                : 'No phones found for ' . $lead->grantee;
        } catch (\Throwable $e) {
            report($e);
            $this->phoneError = 'Phone lookup failed: ' . $e->getMessage();
        }

        $this->phoneLooking = false;
    }

    // ─── Lead Management ─────────────────────────────
    public function updateStatus(int $leadId, string $status)
    {
        AtlasLead::where('id', $leadId)->update(['status' => $status]);
    }

    public function deleteLead(int $leadId)
    {
        AtlasLead::where('id', $leadId)->delete();
        $this->successMessage = 'Lead deleted.';
    }

    public function saveLead()
    {
        $this->validate([
            'formGrantee' => 'required|string|max:255',
            'formGrantor' => 'required|string|max:255',
        ]);

        AtlasLead::create([
            'grantee' => $this->formGrantee,
            'grantor' => $this->formGrantor,
            'county' => $this->formCounty ?: null,
            'state' => $this->formState ?: null,
            'deed_date' => $this->formDate ?: null,
            'address' => $this->formAddress ?: null,
            'instrument' => $this->formInstrument ?: null,
            'deed_type' => $this->formDeedType ?: null,
            'status' => 'new',
            'source' => 'manual',
            'created_by' => auth()->id(),
        ]);

        $this->successMessage = 'Lead added manually.';
        $this->showAddForm = false;
        $this->resetFormFields();
    }

    public function resetFormFields()
    {
        $this->formGrantee = '';
        $this->formGrantor = '';
        $this->formDate = '';
        $this->formAddress = '';
        $this->formCounty = '';
        $this->formState = '';
        $this->formInstrument = '';
        $this->formDeedType = '';
    }

    public function setTabWithCounty(string $tab, int $countyIdx)
    {
        $counties = $this->getCountiesProperty();
        if (isset($counties[$countyIdx])) {
            $c = $counties[$countyIdx];
            if ($tab === 'parser') {
                $this->aiCounty = $c['county'];
                $this->aiState = $c['state'];
            } elseif ($tab === 'pdf') {
                $this->pdfCounty = $c['county'];
                $this->pdfState = $c['state'];
            }
        }
        $this->activeTab = $tab;
    }

    public function clearMessages()
    {
        $this->successMessage = '';
        $this->errorMessage = '';
    }

    // ─── Counties ────────────────────────────────────
    public function getCountiesProperty(): array
    {
        return [
            ['state' => 'FL', 'county' => 'Orange', 'city' => 'Orlando', 'resorts' => 'Westgate, Marriott Grande Vista, Sheraton Vistana, Wyndham Bonnet Creek, Hilton Grand Vacations', 'url' => 'https://or.occompt.com/recorder/web/login.jsp', 'docType' => 'Deed / Official Records', 'tip' => 'Search Official Records → Document Type: DEED → Grantor = resort name'],
            ['state' => 'FL', 'county' => 'Osceola', 'city' => 'Kissimmee', 'resorts' => 'Westgate Town Center, Westgate Lakes, Orange Lake, Mystic Dunes', 'url' => 'https://www.osceolaclerk.com/Official-Records-Search', 'docType' => 'Official Records', 'tip' => 'Official Records → Doc Type = DEED → Search by grantor (resort)'],
            ['state' => 'FL', 'county' => 'Polk', 'city' => 'Davenport', 'resorts' => 'Westgate River Ranch, Fantasy World, Liki Tiki Village', 'url' => 'https://www.polkcountyclerk.net/records-search/', 'docType' => 'Official Records', 'tip' => 'Search Official Records by grantor name'],
            ['state' => 'FL', 'county' => 'Volusia', 'city' => 'Daytona Beach', 'resorts' => 'Wyndham Ocean Walk, Hawaiian Inn', 'url' => 'https://ecr.volusia.org/web/login.jsp', 'docType' => 'Official Records', 'tip' => 'Document Search → Type: DEED'],
            ['state' => 'FL', 'county' => 'Miami-Dade', 'city' => 'Miami Beach', 'resorts' => "Marriott's Villas at Doral", 'url' => 'https://www2.miami-dadeclerk.com/officialrecords/', 'docType' => 'Official Records', 'tip' => 'Search by Doc Type and grantor'],
            ['state' => 'FL', 'county' => 'Broward', 'city' => 'Fort Lauderdale', 'resorts' => "Wyndham Santa Barbara, Marriott's BeachPlace", 'url' => 'https://officialrecords.broward.org/AcclaimWeb/', 'docType' => 'Official Records', 'tip' => 'AcclaimWeb → Doc Type: DEED'],
            ['state' => 'SC', 'county' => 'Horry', 'city' => 'Myrtle Beach', 'resorts' => 'Sheraton Broadway, Marriott OceanWatch, Wyndham Ocean Blvd', 'url' => 'https://horrycounty.org/Online-Services/Register-of-Deeds', 'docType' => 'Deed Records', 'tip' => 'Register of Deeds → Search by grantor'],
            ['state' => 'SC', 'county' => 'Beaufort', 'city' => 'Hilton Head', 'resorts' => "Marriott's SurfWatch, Hilton Grand Vacations", 'url' => 'https://www.beaufortcountysc.gov/register-of-deeds/', 'docType' => 'Deed Records', 'tip' => 'Register of Deeds search'],
            ['state' => 'HI', 'county' => 'Maui', 'city' => 'Lahaina', 'resorts' => "Marriott's Maui Ocean Club, Westin Ka'anapali", 'url' => 'https://boc.ehawaii.gov/lbsearch/', 'docType' => 'Bureau of Conveyances', 'tip' => 'Land Bureau Search → Regular System'],
            ['state' => 'HI', 'county' => 'Honolulu', 'city' => 'Waikiki', 'resorts' => 'Hilton Hawaiian Village, Marriott Ko Olina', 'url' => 'https://boc.ehawaii.gov/lbsearch/', 'docType' => 'Bureau of Conveyances', 'tip' => 'Land Bureau Search → Regular System'],
            ['state' => 'NV', 'county' => 'Clark', 'city' => 'Las Vegas', 'resorts' => "Hilton Grand Vacations, Marriott's Grand Chateau, Wyndham Desert Blue", 'url' => 'https://recorder.clarkcountynv.gov/AcclaimWeb/', 'docType' => 'Official Records', 'tip' => 'AcclaimWeb → Doc Type: DEED TIMESHARE'],
            ['state' => 'MO', 'county' => 'Taney', 'city' => 'Branson', 'resorts' => 'Wyndham Branson, Welk Resorts', 'url' => 'https://www.taneycountyrecorder.com/', 'docType' => 'Deed Records', 'tip' => 'Recorder search for timeshare deed transfers'],
            ['state' => 'CO', 'county' => 'Summit', 'city' => 'Breckenridge', 'resorts' => "Grand Timber Lodge, Marriott's Mountain Valley", 'url' => 'https://www.summitcountyco.gov/recorder', 'docType' => 'Clerk & Recorder', 'tip' => 'Search by grantor for resort names'],
            ['state' => 'CO', 'county' => 'Eagle', 'city' => 'Vail', 'resorts' => "Marriott's StreamSide, Hyatt Residence Club", 'url' => 'https://www.eaglecounty.us/Clerk/Recording/', 'docType' => 'Clerk & Recorder', 'tip' => 'Search grantor for resort-related deeds'],
            ['state' => 'UT', 'county' => 'Summit', 'city' => 'Park City', 'resorts' => "Marriott's MountainSide, Westgate Park City", 'url' => 'https://www.summitcounty.org/215/Recorder', 'docType' => 'Recorder', 'tip' => 'Recorder search → grantor = resort name'],
            ['state' => 'VA', 'county' => 'James City', 'city' => 'Williamsburg', 'resorts' => "Wyndham Kingsgate, Marriott's Manor Club", 'url' => 'https://www.jamescitycountyva.gov/688/Circuit-Court-Clerk', 'docType' => 'Circuit Court', 'tip' => 'Land records search via Circuit Court'],
            ['state' => 'AZ', 'county' => 'Maricopa', 'city' => 'Scottsdale', 'resorts' => "Marriott's Canyon Villas, Westin Kierland", 'url' => 'https://recorder.maricopa.gov/', 'docType' => 'Recorder', 'tip' => 'Search by document type and grantor name'],
            ['state' => 'CA', 'county' => 'Riverside', 'city' => 'Palm Desert', 'resorts' => "Marriott's Desert Springs, Westin Mission Hills", 'url' => 'https://www.rivcoacr.org/', 'docType' => 'Recorder', 'tip' => 'County Recorder search → grant deeds'],
            ['state' => 'CA', 'county' => 'Placer', 'city' => 'Lake Tahoe', 'resorts' => "Marriott's Timber Lodge, Hyatt Regency", 'url' => 'https://www.placer.ca.gov/2925/Recorder', 'docType' => 'Recorder', 'tip' => 'Recorder search → deed transfers'],
        ];
    }

    // ─── Render ──────────────────────────────────────
    public function render()
    {
        $user = auth()->user();
        if (!$user || $user->role !== 'master_admin') {
            abort(403);
        }

        $leads = AtlasLead::query()
            ->when($this->filterStatus !== 'ALL', fn($q) => $q->where('status', $this->filterStatus))
            ->when($this->searchQuery, fn($q) => $q->search($this->searchQuery))
            ->orderByDesc('created_at')
            ->paginate(25);

        $stats = [
            'total' => AtlasLead::count(),
            'new' => AtlasLead::where('status', 'new')->count(),
            'searched' => AtlasLead::where('status', 'searched')->count(),
            'traced' => AtlasLead::where('status', 'traced')->count(),
            'imported' => AtlasLead::where('status', 'imported')->count(),
            'ai_parsed' => AtlasLead::whereIn('source', ['ai-text', 'ai-pdf'])->count(),
            'with_phone' => AtlasLead::whereNotNull('phone_1')->count(),
        ];

        $recentLogs = AtlasParseLog::with('user')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $untracedLeads = AtlasLead::whereNull('phone_1')
            ->where('status', '!=', 'imported')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $counties = $this->getCountiesProperty();
        $filteredCounties = collect($counties)->filter(function ($c) {
            if (!$this->countySearch) return true;
            $s = strtolower($this->countySearch);
            return str_contains(strtolower($c['county']), $s)
                || str_contains(strtolower($c['state']), $s)
                || str_contains(strtolower($c['city']), $s)
                || str_contains(strtolower($c['resorts']), $s);
        })->values();

        return view('livewire.atlas-dashboard', compact(
            'leads', 'stats', 'recentLogs', 'untracedLeads', 'counties', 'filteredCounties'
        ));
    }
}
