<?php

namespace App\Jobs\Finance;

use App\Models\MerchantStatement;
use App\Services\Finance\StatementValidationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Validates a parsed statement's data consistency.
 */
class ValidateMerchantStatementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(public int $statementId)
    {
        $this->onQueue('finance');
    }

    public function handle(): void
    {
        $statement = MerchantStatement::find($this->statementId);
        if (!$statement || $statement->ai_parse_status !== 'completed') return;

        StatementValidationService::validate($statement);
    }
}
