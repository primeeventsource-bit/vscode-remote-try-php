<?php

namespace App\Livewire\Finance;

use App\Models\MerchantAccount;
use App\Services\Finance\FinanceDashboardService;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Finance Command Center')]
class FinanceDashboard extends Component
{
    public string $dateRange = '30d';
    public string $midFilter = 'all';

    public function render()
    {
        $user = auth()->user();
        if (!$user->hasRole('master_admin') && !$user->hasPerm('view_finance')) {
            abort(403);
        }

        [$from, $to] = $this->computeDateRange();
        $midId = $this->midFilter !== 'all' ? (int) $this->midFilter : null;

        $dashboard = [];
        $mids = collect();

        try {
            $dashboard = FinanceDashboardService::getFullDashboard($midId, $from, $to);
            $mids = MerchantAccount::active()->orderBy('account_name')->get();
        } catch (\Throwable $e) {
            report($e);
        }

        return view('livewire.finance.dashboard', [
            'dashboard' => $dashboard,
            'mids' => $mids,
            'summaryCards' => $dashboard['summary_cards'] ?? [],
            'profitability' => $dashboard['profitability'] ?? [],
            'midBreakdown' => $dashboard['mid_breakdown'] ?? [],
            'chargebackSummary' => $dashboard['chargeback_summary'] ?? [],
            'transactionTrends' => $dashboard['transaction_trends'] ?? [],
            'financialBurden' => $dashboard['financial_burden'] ?? [],
            'importHealth' => $dashboard['statement_import_health'] ?? [],
        ]);
    }

    private function computeDateRange(): array
    {
        $now = Carbon::now();
        return match ($this->dateRange) {
            'today' => [$now->copy()->startOfDay()->toDateString(), $now->toDateString()],
            '7d' => [$now->copy()->subDays(7)->toDateString(), $now->toDateString()],
            '30d' => [$now->copy()->subDays(30)->toDateString(), $now->toDateString()],
            'month' => [$now->copy()->startOfMonth()->toDateString(), $now->toDateString()],
            'quarter' => [$now->copy()->subMonths(3)->startOfMonth()->toDateString(), $now->toDateString()],
            'year' => [$now->copy()->startOfYear()->toDateString(), $now->toDateString()],
            'all' => [null, null],
            default => [$now->copy()->subDays(30)->toDateString(), $now->toDateString()],
        };
    }
}
