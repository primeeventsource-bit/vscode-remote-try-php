<?php

namespace App\Livewire;

use App\Models\CallSession;
use App\Models\Objection;
use App\Models\ObjectionLog;
use App\Models\User;
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

    public function detectObjections(): void
    {
        if (!$this->ready() || !$this->searchText) return;
        // Detection handled in render via Objection::detectFromText
    }

    public function selectObjection(int $id): void
    {
        $this->selectedObjectionId = $id;
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

            if ($this->searchText) {
                $detectedObjections = Objection::detectFromText($this->searchText);
            }

            if ($this->selectedObjectionId) {
                // keep selection
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
