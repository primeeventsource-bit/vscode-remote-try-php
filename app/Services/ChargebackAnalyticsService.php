<?php

namespace App\Services;

use App\Models\Chargeback;
use App\Models\Deal;
use App\Models\MerchantAccount;
use App\Models\Processor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ChargebackAnalyticsService
{
    public function parsePeriod(string $period = 'last_30_days', ?string $startDate = null, ?string $endDate = null): array
    {
        $today = Carbon::today();

        return match ($period) {
            'today' => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
            'last_7_days' => [$today->copy()->subDays(6)->startOfDay(), $today->copy()->endOfDay()],
            'this_month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'last_3_months' => [$today->copy()->subMonths(2)->startOfMonth(), $today->copy()->endOfDay()],
            'last_6_months' => [$today->copy()->subMonths(5)->startOfMonth(), $today->copy()->endOfDay()],
            'last_12_months' => [$today->copy()->subMonths(11)->startOfMonth(), $today->copy()->endOfDay()],
            'custom' => [
                Carbon::parse($startDate ?? $today->copy()->subDays(29)->toDateString())->startOfDay(),
                Carbon::parse($endDate ?? $today->toDateString())->endOfDay(),
            ],
            default => [$today->copy()->subDays(29)->startOfDay(), $today->copy()->endOfDay()],
        };
    }

    public function previousPeriod(Carbon $start, Carbon $end): array
    {
        $days = $start->diffInDays($end) + 1;
        $prevEnd = $start->copy()->subDay()->endOfDay();
        $prevStart = $prevEnd->copy()->subDays($days - 1)->startOfDay();

        return [$prevStart, $prevEnd];
    }

    public function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(!empty($filters['start_date']) && !empty($filters['end_date']), function (Builder $q) use ($filters): void {
                $q->whereBetween('dispute_date', [$filters['start_date'], $filters['end_date']]);
            })
            ->when(!empty($filters['processor_id']), fn (Builder $q): Builder => $q->where('processor_id', $filters['processor_id']))
            ->when(!empty($filters['sales_rep_id']), fn (Builder $q): Builder => $q->where('sales_rep_id', $filters['sales_rep_id']))
            ->when(!empty($filters['merchant_account_id']), fn (Builder $q): Builder => $q->where('merchant_account_id', $filters['merchant_account_id']))
            ->when(!empty($filters['status']), fn (Builder $q): Builder => $q->where('status', $filters['status']))
            ->when(!empty($filters['reason_code']), fn (Builder $q): Builder => $q->where('reason_code', $filters['reason_code']))
            ->when(!empty($filters['card_brand']), fn (Builder $q): Builder => $q->where('card_brand', $filters['card_brand']))
            ->when(!empty($filters['product_id']), fn (Builder $q): Builder => $q->where('product_id', $filters['product_id']))
            ->when(!empty($filters['payment_method']), fn (Builder $q): Builder => $q->where('payment_method', $filters['payment_method']));
    }

    public function summary(array $filters, Carbon $start, Carbon $end, Carbon $prevStart, Carbon $prevEnd): array
    {
        $current = $this->applyFilters(Chargeback::query(), array_merge($filters, [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ]));

        $previous = $this->applyFilters(Chargeback::query(), array_merge($filters, [
            'start_date' => $prevStart->toDateString(),
            'end_date' => $prevEnd->toDateString(),
        ]));

        $currentCount = (int) $current->count();
        $previousCount = (int) $previous->count();

        $currentAmount = (float) $current->sum('chargeback_amount');
        $previousAmount = (float) $previous->sum('chargeback_amount');

        $currentWon = (int) (clone $current)->whereIn('outcome', ['won', 'reversed'])->count();
        $currentLost = (int) (clone $current)->where('outcome', 'lost')->count();
        $currentPending = (int) (clone $current)->whereIn('status', ['pending', 'under_review'])->count();
        $currentRefundedBefore = (int) (clone $current)->where('refunded_before_dispute', true)->count();
        $currentPrevented = (int) (clone $current)->where('outcome', 'prevented')->count();

        $grossProcessed = (float) Deal::query()
            ->whereBetween('timestamp', [$start->toDateString(), $end->toDateString()])
            ->sum('fee');

        $representmentDenom = max(1, $currentWon + $currentLost);
        $representmentRate = ($currentWon / $representmentDenom) * 100;

        $chargebackRatio = $grossProcessed > 0 ? ($currentAmount / $grossProcessed) * 100 : 0;

        return [
            'total_chargebacks' => $this->metric($currentCount, $previousCount),
            'total_chargeback_amount' => $this->metric($currentAmount, $previousAmount),
            'chargeback_ratio_pct' => [
                'current' => $chargebackRatio,
                'previous' => 0,
                'change_pct' => 0,
                'trend' => $chargebackRatio >= 0 ? 'up' : 'down',
            ],
            'pending_chargebacks' => ['current' => $currentPending],
            'won_chargebacks' => ['current' => $currentWon],
            'lost_chargebacks' => ['current' => $currentLost],
            'refunded_before_dispute' => ['current' => $currentRefundedBefore],
            'prevented_recovered' => ['current' => $currentPrevented],
            'representment_success_rate_pct' => ['current' => $representmentRate],
            'gross_processed_volume' => $grossProcessed,
        ];
    }

    public function trends(array $filters, Carbon $start, Carbon $end): array
    {
        $rows = $this->applyFilters(Chargeback::query(), array_merge($filters, [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ]))
            ->selectRaw('DATE(dispute_date) as d, COUNT(*) as c, SUM(chargeback_amount) as amt')
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        $byDate = $rows->keyBy('d');
        $series = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $row = $byDate->get($key);
            $series[] = [
                'date' => $key,
                'count' => (int) ($row->c ?? 0),
                'amount' => (float) ($row->amt ?? 0),
            ];
            $cursor->addDay();
        }

        return ['series' => $series];
    }

    public function breakdowns(array $filters, Carbon $start, Carbon $end): array
    {
        $base = $this->applyFilters(Chargeback::query(), array_merge($filters, [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ]));

        $make = function (string $field) use ($base): Collection {
            return (clone $base)
                ->selectRaw($field . ' as label, COUNT(*) as count, SUM(chargeback_amount) as amount')
                ->groupBy($field)
                ->orderByDesc('count')
                ->limit(10)
                ->get()
                ->map(fn ($r) => ['label' => $r->label ?: 'Unknown', 'count' => (int) $r->count, 'amount' => (float) $r->amount]);
        };

        $mapLabel = function (Collection $rows, Collection $lookup): Collection {
            return $rows->map(function (array $row) use ($lookup): array {
                $key = (string) $row['label'];
                if ($key !== 'Unknown' && $lookup->has($key)) {
                    $row['label'] = $lookup->get($key);
                }

                return $row;
            });
        };

        $processorLookup = Processor::query()->pluck('name', 'id')->mapWithKeys(fn ($v, $k) => [(string) $k => $v]);
        $salesRepLookup = User::query()->pluck('name', 'id')->mapWithKeys(fn ($v, $k) => [(string) $k => $v]);
        $merchantLookup = MerchantAccount::query()->pluck('name', 'id')->mapWithKeys(fn ($v, $k) => [(string) $k => $v]);

        return [
            'by_processor' => $mapLabel($make('processor_id'), $processorLookup),
            'by_sales_rep' => $mapLabel($make('sales_rep_id'), $salesRepLookup),
            'by_merchant_account' => $mapLabel($make('merchant_account_id'), $merchantLookup),
            'by_product' => $make('product_id'),
            'by_source' => $make('source_system'),
            'by_payment_type' => $make('payment_method'),
            'by_card_brand' => $make('card_brand'),
            'by_reason_code' => $make('reason_code'),
            'by_status' => $make('status'),
        ];
    }

    public function filterOptions(): array
    {
        return [
            'processors' => \App\Models\Processor::query()->select('id', 'name')->orderBy('name')->get(),
            'sales_reps' => \App\Models\User::query()->select('id', 'name')->orderBy('name')->get(),
            'merchant_accounts' => \App\Models\MerchantAccount::query()->select('id', 'name')->orderBy('name')->get(),
            'statuses' => Chargeback::query()->select('status')->distinct()->pluck('status')->filter()->values(),
            'reason_codes' => Chargeback::query()->select('reason_code')->distinct()->pluck('reason_code')->filter()->values(),
            'card_brands' => Chargeback::query()->select('card_brand')->distinct()->pluck('card_brand')->filter()->values(),
            'payment_methods' => Chargeback::query()->select('payment_method')->distinct()->pluck('payment_method')->filter()->values(),
        ];
    }

    private function metric(float|int $current, float|int $previous): array
    {
        $denom = $previous == 0 ? 1.0 : (float) $previous;
        $change = (($current - $previous) / $denom) * 100;

        return [
            'current' => $current,
            'previous' => $previous,
            'change_pct' => $change,
            'trend' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat'),
        ];
    }
}
