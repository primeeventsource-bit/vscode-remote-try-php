<?php

namespace App\Livewire;

use App\Models\CallSession;
use App\Models\Objection;
use App\Models\ObjectionLog;
use App\Models\User;
use App\Services\AI\AIEngineService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Sales Training')]
class SalesTraining extends Component
{
    public string $tab = 'live_assist';
    public string $searchText = '';
    public string $selectedCategory = '';
    public ?int $selectedObjectionId = null;
    public ?int $activeSessionId = null;

    // Objection management (admin)
    public bool $showAddObjection = false;
    public array $objectionForm = [
        'category' => 'money', 'objection_text' => '', 'keywords' => '',
        'rebuttal_level_1' => '', 'rebuttal_level_2' => '', 'rebuttal_level_3' => '',
    ];

    // Live assist
    public string $noteInput = '';

    private function ready(): bool
    {
        return Schema::hasTable('objection_library');
    }

    // ── Live Assist ─────────────────────────────────────────

    public string $aiNextLine = '';
    public string $aiStatus = '';

    public function detectObjections(): void
    {
        // Detection is handled in render() — this just triggers a re-render
    }

    public function getAiNextLine(): void
    {
        $this->aiStatus = 'loading';
        $this->aiNextLine = '';

        try {
            $objection = $this->selectedObjectionId ? Objection::find($this->selectedObjectionId)?->objection_text : $this->searchText;
            $result = AIEngineService::suggestNextLine(
                auth()->user(),
                'closer',
                'closer',
                $objection,
                'handling client objection'
            );
            $this->aiNextLine = $result['line'] ?? $result['text'] ?? 'No suggestion available.';
            $this->aiStatus = 'ready';
        } catch (\Throwable $e) {
            $this->aiNextLine = 'AI unavailable — use rebuttals from the library.';
            $this->aiStatus = 'error';
        }
    }

    public function selectObjection(int $id): void
    {
        $this->selectedObjectionId = $id;
    }

    public function selectCategory(string $cat): void
    {
        $this->selectedCategory = $this->selectedCategory === $cat ? '' : $cat;
        $this->selectedObjectionId = null;
    }

    public function logRebuttalUsed(int $objectionId, string $level, string $rebuttalText): void
    {
        if (!$this->ready()) return;
        $user = auth()->user();

        // Create or get active session
        if (!$this->activeSessionId) {
            $session = CallSession::create([
                'user_id' => $user->id,
                'current_stage' => in_array($user->role, ['fronter', 'fronter_panama']) ? 'fronter' : 'closer',
                'status' => 'active',
            ]);
            $this->activeSessionId = $session->id;
        }

        ObjectionLog::create([
            'call_session_id' => $this->activeSessionId,
            'objection_id' => $objectionId,
            'objection_text' => Objection::find($objectionId)?->objection_text ?? '',
            'selected_rebuttal' => $rebuttalText,
            'rebuttal_level' => $level,
            'result' => 'pending',
            'user_id' => $user->id,
        ]);

        CallSession::where('id', $this->activeSessionId)->increment('objection_count');
    }

    public function markResult(int $logId, string $result): void
    {
        if (!$this->ready()) return;
        ObjectionLog::where('id', $logId)->where('user_id', auth()->id())->update(['result' => $result]);
    }

    public function endSession(string $status = 'closed'): void
    {
        if ($this->activeSessionId) {
            CallSession::where('id', $this->activeSessionId)->update(['status' => $status]);
            $this->activeSessionId = null;
        }
    }

    // ── Objection Library Management (Admin) ────────────────

    public function saveObjection(): void
    {
        if (!auth()->user()?->hasRole('master_admin', 'admin')) return;
        if (!$this->ready()) return;

        if (!trim($this->objectionForm['objection_text'])) return;

        Objection::create([
            'objection_text' => $this->objectionForm['objection_text'],
            'category' => $this->objectionForm['category'],
            'keywords' => $this->objectionForm['keywords'],
            'rebuttal_level_1' => $this->objectionForm['rebuttal_level_1'] ?: null,
            'rebuttal_level_2' => $this->objectionForm['rebuttal_level_2'] ?: null,
            'rebuttal_level_3' => $this->objectionForm['rebuttal_level_3'] ?: null,
            'created_by' => auth()->id(),
        ]);

        $this->objectionForm = ['category' => 'money', 'objection_text' => '', 'keywords' => '', 'rebuttal_level_1' => '', 'rebuttal_level_2' => '', 'rebuttal_level_3' => ''];
        $this->showAddObjection = false;
    }

    public function toggleObjection(int $id): void
    {
        if (!auth()->user()?->hasRole('master_admin', 'admin')) return;
        $obj = Objection::find($id);
        if ($obj) $obj->update(['is_active' => !$obj->is_active]);
    }

    // ── Render ───────────────────────────────────────────────

    public function render()
    {
        $user = auth()->user();
        $isAdmin = $user->hasRole('master_admin', 'admin');

        $objections = collect();
        $detectedObjections = collect();
        $analytics = ['close_rate' => 0, 'total_sessions' => 0, 'won' => 0, 'lost' => 0, 'top_objections' => [], 'best_rebuttals' => [], 'rep_ranking' => []];
        $recentLogs = collect();

        if ($this->ready()) {
            $objections = Objection::where('is_active', true)->orderBy('category')->get();

            // Start with all objections, then narrow
            $pool = $objections;

            // Category filter
            if ($this->selectedCategory) {
                $pool = $pool->where('category', $this->selectedCategory)->values();
                $detectedObjections = $pool;
            }

            // Text search — keyword matching + AI (works with or without category)
            if ($this->searchText && strlen(trim($this->searchText)) >= 2) {
                // Step 1: Local keyword match (within category pool if set)
                $searchPool = $this->selectedCategory ? $pool : $objections;
                $text = strtolower($this->searchText);
                $detectedObjections = $searchPool->filter(function ($obj) use ($text) {
                    $keywords = array_map('trim', explode(',', strtolower($obj->keywords ?? '')));
                    foreach ($keywords as $kw) {
                        if ($kw && str_contains($text, $kw)) return true;
                    }
                    // Also match objection text itself
                    return str_contains(strtolower($obj->objection_text), $text);
                })->values();

                // Step 2: If no local match, try AI detection
                if ($detectedObjections->isEmpty()) {
                    try {
                        $aiResult = AIEngineService::detectObjection(auth()->user(), $this->searchText);
                        if (!empty($aiResult['category']) && $aiResult['category'] !== null) {
                            // Find matching objection from library by category
                            $aiMatch = $objections->where('category', $aiResult['category'])->first();
                            if ($aiMatch) {
                                $detectedObjections = collect([$aiMatch]);
                                if (!$this->selectedObjectionId) {
                                    $this->selectedObjectionId = $aiMatch->id;
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        // AI failed — no problem, local results still show
                    }
                }

                // Step 3: If still no match, show pool or top 5
                if ($detectedObjections->isEmpty()) {
                    $detectedObjections = $this->selectedCategory ? $pool : $objections->take(5);
                }

                // Auto-select first match if none selected
                if (!$this->selectedObjectionId && $detectedObjections->isNotEmpty()) {
                    $this->selectedObjectionId = $detectedObjections->first()->id;
                }
            }

            // Auto-select first result if nothing selected
            if (!$this->selectedObjectionId && $detectedObjections->isNotEmpty()) {
                $this->selectedObjectionId = $detectedObjections->first()->id;
            }

            // Recent logs for active session
            if ($this->activeSessionId) {
                $recentLogs = ObjectionLog::where('call_session_id', $this->activeSessionId)
                    ->orderByDesc('id')->limit(10)->get();
            }

            // Analytics
            if ($this->tab === 'analytics') {
                try {
                    $totalSessions = CallSession::count();
                    $won = ObjectionLog::where('result', 'won')->count();
                    $lost = ObjectionLog::where('result', 'lost')->count();
                    $total = max(1, $won + $lost);

                    $topObjections = DB::table('objection_logs')
                        ->select('objection_text', DB::raw('COUNT(*) as cnt'))
                        ->groupBy('objection_text')
                        ->orderByDesc('cnt')
                        ->limit(5)
                        ->get()
                        ->map(fn($r) => ['text' => $r->objection_text, 'count' => $r->cnt])
                        ->toArray();

                    $bestRebuttals = DB::table('objection_logs')
                        ->select('selected_rebuttal', 'rebuttal_level', DB::raw('SUM(CASE WHEN result = \'won\' THEN 1 ELSE 0 END) as wins'), DB::raw('COUNT(*) as total'))
                        ->whereNotNull('selected_rebuttal')
                        ->groupBy('selected_rebuttal', 'rebuttal_level')
                        ->orderByDesc('wins')
                        ->limit(5)
                        ->get()
                        ->map(fn($r) => ['text' => substr($r->selected_rebuttal, 0, 80), 'level' => $r->rebuttal_level, 'wins' => $r->wins, 'total' => $r->total, 'pct' => $r->total > 0 ? round($r->wins / $r->total * 100) : 0])
                        ->toArray();

                    $repRanking = DB::table('objection_logs')
                        ->join('users', 'objection_logs.user_id', '=', 'users.id')
                        ->select('users.name', 'users.color', 'users.avatar',
                            DB::raw('COUNT(*) as total'),
                            DB::raw('SUM(CASE WHEN result = \'won\' THEN 1 ELSE 0 END) as wins'))
                        ->groupBy('users.id', 'users.name', 'users.color', 'users.avatar')
                        ->orderByDesc('wins')
                        ->limit(10)
                        ->get()
                        ->map(fn($r) => ['name' => $r->name, 'color' => $r->color, 'avatar' => $r->avatar, 'wins' => $r->wins, 'total' => $r->total, 'pct' => $r->total > 0 ? round($r->wins / $r->total * 100) : 0])
                        ->toArray();

                    $analytics = [
                        'close_rate' => round($won / $total * 100, 1),
                        'total_sessions' => $totalSessions,
                        'won' => $won,
                        'lost' => $lost,
                        'top_objections' => $topObjections,
                        'best_rebuttals' => $bestRebuttals,
                        'rep_ranking' => $repRanking,
                    ];
                } catch (\Throwable $e) {}
            }
        }

        $selectedObjection = $this->selectedObjectionId ? Objection::find($this->selectedObjectionId) : null;

        return view('livewire.sales-training', compact(
            'objections', 'detectedObjections', 'selectedObjection',
            'isAdmin', 'analytics', 'recentLogs'
        ));
    }
}
