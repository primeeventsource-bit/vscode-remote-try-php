<?php

namespace App\Jobs\Finance;

use App\Models\MerchantAccount;
use App\Models\MerchantStatement;
use App\Services\Finance\ProfitCalculatorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Rebuilds profit snapshots for a merchant account + month.
 */
class RebuildMerchantProfitSnapshotsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 180;

    public function __construct(
        public int $merchantAccountId,
        public string $month,
    ) {
        $this->onQueue('finance');
    }

    public function handle(): void
    {
        $merchant = MerchantAccount::find($this->merchantAccountId);
        if (!$merchant) return;

        $hasStatement = MerchantStatement::completed()
            ->where('merchant_account_id', $this->merchantAccountId)
            ->where('statement_month', $this->month)
            ->exists();

        if ($hasStatement) {
            ProfitCalculatorService::snapshot($this->merchantAccountId, $this->month);
        }
    }
}
