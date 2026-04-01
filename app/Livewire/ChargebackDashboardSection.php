<?php

namespace App\Livewire;

use App\Services\ChargebackAnalyticsService;
use Livewire\Component;

class ChargebackDashboardSection extends Component
{
    public string $period = 'last_7_days';
    public string $startDate = '';
    public string $endDate = '';
    public string $processorId = '';
    public string $salesRepId = '';
    public string $merchantAccountId = '';
    public string $status = '';
    public string $reasonCode = '';
    public string $cardBrand = '';
    public string $productId = '';
    public string $paymentMethod = '';

    protected $queryString = [
        'period' => ['except' => 'last_7_days', 'as' => 'cb_period'],
        'startDate' => ['except' => '', 'as' => 'cb_start_date'],
        'endDate' => ['except' => '', 'as' => 'cb_end_date'],
        'processorId' => ['except' => '', 'as' => 'cb_processor_id'],
        'salesRepId' => ['except' => '', 'as' => 'cb_sales_rep_id'],
        'merchantAccountId' => ['except' => '', 'as' => 'cb_merchant_account_id'],
        'status' => ['except' => '', 'as' => 'cb_status'],
        'reasonCode' => ['except' => '', 'as' => 'cb_reason_code'],
        'cardBrand' => ['except' => '', 'as' => 'cb_card_brand'],
        'productId' => ['except' => '', 'as' => 'cb_product_id'],
        'paymentMethod' => ['except' => '', 'as' => 'cb_payment_method'],
    ];

    public function resetFilters(): void
    {
        $this->period = 'last_7_days';
        $this->startDate = '';
        $this->endDate = '';
        $this->processorId = '';
        $this->salesRepId = '';
        $this->merchantAccountId = '';
        $this->status = '';
        $this->reasonCode = '';
        $this->cardBrand = '';
        $this->productId = '';
        $this->paymentMethod = '';
    }

    public function render(ChargebackAnalyticsService $analytics)
    {
        [$start, $end] = $analytics->parsePeriod($this->period, $this->startDate ?: null, $this->endDate ?: null);
        [$prevStart, $prevEnd] = $analytics->previousPeriod($start, $end);

        $filters = [
            'processor_id' => $this->processorId,
            'sales_rep_id' => $this->salesRepId,
            'merchant_account_id' => $this->merchantAccountId,
            'status' => $this->status,
            'reason_code' => $this->reasonCode,
            'card_brand' => $this->cardBrand,
            'product_id' => $this->productId,
            'payment_method' => $this->paymentMethod,
        ];

        $summary = $analytics->summary($filters, $start, $end, $prevStart, $prevEnd);
        $trends = $analytics->trends($filters, $start, $end);
        $breakdowns = $analytics->breakdowns($filters, $start, $end);
        $options = $analytics->filterOptions();

        $managementQuery = array_filter([
            'startDate' => $start->toDateString(),
            'endDate' => $end->toDateString(),
            'processorId' => $this->processorId,
            'salesRepId' => $this->salesRepId,
            'merchantAccountId' => $this->merchantAccountId,
            'status' => $this->status,
            'reasonCode' => $this->reasonCode,
            'cardBrand' => $this->cardBrand,
            'paymentMethod' => $this->paymentMethod,
        ], fn ($v) => $v !== '');

        return view('livewire.chargeback-dashboard-section', [
            'summary' => $summary,
            'trends' => $trends,
            'breakdowns' => $breakdowns,
            'options' => $options,
            'rangeLabel' => $start->format('M j, Y') . ' - ' . $end->format('M j, Y'),
            'managementQuery' => $managementQuery,
        ]);
    }
}
