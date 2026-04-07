<?php

namespace App\Services\Dashboard;

use Carbon\Carbon;

/**
 * Immutable filter value object for all dashboard queries.
 * Ensures consistent filter application across every service method.
 */
class DashboardFilterData
{
    public readonly Carbon $from;
    public readonly Carbon $to;
    public readonly ?int $ownerId;
    public readonly ?string $status;
    public readonly string $dateRangeKey;

    public function __construct(
        string $dateRange = '30d',
        ?int $ownerId = null,
        ?string $status = null,
    ) {
        $this->dateRangeKey = $dateRange;
        $this->ownerId = $ownerId;
        $this->status = $status;
        $this->to = now();

        $this->from = match ($dateRange) {
            'today' => now()->startOfDay(),
            '7d'    => now()->subDays(7),
            'month' => now()->startOfMonth(),
            default => now()->subDays(30),
        };
    }

    public function toMeta(): array
    {
        return [
            'date_range' => $this->dateRangeKey,
            'owner_id' => $this->ownerId,
            'status' => $this->status,
        ];
    }
}
