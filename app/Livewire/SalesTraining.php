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
    public ?int $selectedScriptId = null;
    public ?int $selectedModuleId = null;

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
        try {
            return Schema::hasTable('objection_library');
        } catch (\Throwable $e) {
            return false;
        }
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

    private function seedDefaultObjections(): void
    {
        $defaults = [
            ['category' => 'money', 'objection_text' => "It's too expensive / I can't afford it", 'keywords' => 'expensive,afford,cost,money,price,budget',
             'rebuttal_level_1' => "I understand — let me show you how this actually saves you money compared to what you're paying now in maintenance fees.",
             'rebuttal_level_2' => "If I could show you how to eliminate your annual fees and actually put money back in your pocket, would that change things?",
             'rebuttal_level_3' => "You're already spending thousands on something you don't use. We're offering a way out that costs less than one year of your current fees."],
            ['category' => 'timing', 'objection_text' => "Call me back later / Not a good time", 'keywords' => 'later,busy,call back,not now,bad time',
             'rebuttal_level_1' => "Absolutely, when would be a better time?",
             'rebuttal_level_2' => "I completely understand. But this offer is time-sensitive. Can I take just 2 minutes to explain?",
             'rebuttal_level_3' => "Every day you wait, you're paying fees on something you don't use. Two minutes now could save you thousands."],
            ['category' => 'spouse', 'objection_text' => "I need to talk to my spouse first", 'keywords' => 'spouse,wife,husband,partner,discuss',
             'rebuttal_level_1' => "Of course — would it help if I prepared a quick summary you can share with them?",
             'rebuttal_level_2' => "Would your spouse want to keep paying fees on something you're not using? Let me get the details ready.",
             'rebuttal_level_3' => "What if we get everything set up now, and you have 3 days to finalize? That way you lock in today's rate."],
            ['category' => 'trust', 'objection_text' => "How do I know this is legitimate?", 'keywords' => 'scam,legit,trust,real,verify,legitimate',
             'rebuttal_level_1' => "Great question — here's our company information and BBB rating.",
             'rebuttal_level_2' => "Let me verify everything with you right now — our licensing, our address, and connect you with verification.",
             'rebuttal_level_3' => "We're fully licensed, bonded, and regulated. We don't ask for payment until you've verified us completely."],
            ['category' => 'thinking', 'objection_text' => "I need to think about it", 'keywords' => 'think,consider,decide,sleep on it',
             'rebuttal_level_1' => "Of course — what specifically would you like to think about?",
             'rebuttal_level_2' => "What usually happens is people think about it, then the offer expires. What part is making you hesitate?",
             'rebuttal_level_3' => "90% of people who say they'll think about it never call back — and they lose another year of fees. What's the one thing holding you back?"],
            ['category' => 'interest', 'objection_text' => "I'm not interested", 'keywords' => 'not interested,no thanks,pass,decline',
             'rebuttal_level_1' => "Before I go — are you currently using your timeshare, or is it sitting unused?",
             'rebuttal_level_2' => "Are you happy paying fees every year for something you don't use? Because that's exactly what we help people stop doing.",
             'rebuttal_level_3' => "If I could show you how to legally stop paying fees for a one-time cost less than one year of fees — would that be worth 5 minutes?"],
            ['category' => 'card', 'objection_text' => "I don't want to give my card info", 'keywords' => 'card,credit card,payment,secure',
             'rebuttal_level_1' => "Completely understandable. Your information is processed through a secure, encrypted system.",
             'rebuttal_level_2' => "We use bank-level encryption. Your card is only used for the one-time processing fee. Nothing recurring.",
             'rebuttal_level_3' => "Your card info is safer with us than at a gas station. We're PCI compliant with encrypted processing."],
            ['category' => 'competitor', 'objection_text' => "I've already tried another company", 'keywords' => 'tried,another company,before,didn\'t work',
             'rebuttal_level_1' => "I'm sorry that didn't work out. We do things differently.",
             'rebuttal_level_2' => "That's exactly why you should talk to us. We specialize in cases where others have failed.",
             'rebuttal_level_3' => "Most of our clients come to us AFTER another company failed. We're the solution to that problem."],
        ];

        foreach ($defaults as $d) {
            try {
                Objection::firstOrCreate(
                    ['category' => $d['category']],
                    array_merge($d, ['is_active' => true])
                );
            } catch (\Throwable $e) {}
        }
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
            try {
                $objections = Objection::where('is_active', true)->orderBy('category')->get();
            } catch (\Throwable $e) {
                $objections = collect();
            }

            // If table exists but is empty, seed it now
            if ($objections->isEmpty()) {
                try {
                    $this->seedDefaultObjections();
                    $objections = Objection::where('is_active', true)->orderBy('category')->get();
                } catch (\Throwable $e) {}
            }

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

        // Ensure full PDF scripts exist in DB (self-healing)
        \App\Services\ScriptSeeder::ensureScriptsExist();

        // Scripts — load all active, defaults first
        $scripts = collect();
        $selectedScript = null;
        try {
            $scripts = \App\Models\SalesScript::where('is_active', true)
                ->orderBy('order_index')
                ->get();

            // Sort defaults first if column exists
            if (\App\Models\SalesScript::hasDefaultColumn()) {
                $scripts = $scripts->sortByDesc('is_default')->values();
            }

            if ($this->selectedScriptId) {
                $selectedScript = \App\Models\SalesScript::find($this->selectedScriptId);
            } else {
                // Auto-select the default script for the user's stage
                $userStage = in_array($user->role, ['fronter', 'fronter_panama']) ? 'fronter' : 'closer';
                $default = \App\Models\SalesScript::defaultForStage($userStage);
                if ($default) {
                    $this->selectedScriptId = $default->id;
                    $selectedScript = $default;
                } elseif ($scripts->isNotEmpty()) {
                    $this->selectedScriptId = $scripts->first()->id;
                    $selectedScript = $scripts->first();
                }
            }
        } catch (\Throwable $e) {}

        // Training modules
        $modules = collect();
        $selectedModule = null;
        try {
            $modules = \App\Models\TrainingModule::where('is_active', true)->orderBy('order_index')->get();
            $selectedModule = $this->selectedModuleId ? \App\Models\TrainingModule::find($this->selectedModuleId) : null;
        } catch (\Throwable $e) {}

        return view('livewire.sales-training', compact(
            'objections', 'detectedObjections', 'selectedObjection',
            'isAdmin', 'analytics', 'recentLogs',
            'scripts', 'selectedScript', 'modules', 'selectedModule'
        ));
    }
}
