<?php

namespace Tests\Feature\Payroll;

use App\Models\Deal;
use App\Models\DealFinancial;
use App\Models\PayrollBatchDeal;
use App\Models\PayrollBatchV2;
use App\Models\User;
use App\Services\Payroll\PayrollBatchBuilder;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins double-pay prevention in the v2 payroll batch flow.
 *
 * Two layers of defence are tested:
 *   1. payroll_batch_deals.unique([payroll_batch_id, deal_id]) — DB-level;
 *      already in migration 2026_04_08_100002 line 165. Prevents the same
 *      deal landing twice in the SAME batch.
 *   2. PayrollBatchBuilder::attachEligibleDeals() exclusion list — service-
 *      level; broadened in 2026-05 to cover draft/approved/locked/paid.
 *      Prevents the same deal landing in a SECOND batch while it's still
 *      claimed by an existing one.
 *
 * Without (2), running buildWeeklyBatch() twice — even by accident, even
 * before the first batch is locked — would attach each eligible deal to
 * both batches with different (payroll_batch_id, deal_id) pairs (so (1)
 * doesn't catch it). Approving and paying both batches would then double
 * the payout.
 */
class PayrollBatchBuilderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $fronter;
    private User $closer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makeUser('admin');
        $this->fronter = $this->makeUser('fronter');
        $this->closer = $this->makeUser('closer');
        // PayrollBatchBuilder uses auth()->id() for created_by; make sure
        // the calls don't blow up with a null FK on `users.id`.
        $this->actingAs($this->admin);
    }

    private function makeUser(string $role): User
    {
        $u = User::create([
            'name'     => "Fixture {$role}",
            'email'    => "{$role}_".uniqid().'@fixture.example',
            'username' => "u_{$role}_".uniqid(),
            'password' => bcrypt('x'),
            'avatar'   => 'XX',
            'color'    => '#000000',
            'status'   => 'online',
        ]);
        $u->role = $role;
        $u->permissions = [];
        $u->save();
        return $u->fresh();
    }

    /**
     * Create an "eligible" deal — paid in the given week, financial snapshot
     * present, status calculated, not reversed.
     */
    private function makeEligibleDeal(Carbon $paymentDate): Deal
    {
        $deal = new Deal();
        $deal->forceFill([
            'owner_name'             => 'Customer '.uniqid(),
            'fee'                    => 5000,
            'gross_amount'           => 5000,
            'collected_amount'       => 5000,
            'payment_date'           => $paymentDate,
            'payroll_status'         => 'calculated',
            'commission_status'      => 'calculated',
            'fronter_user_id'        => $this->fronter->id,
            'closer_user_id_payroll' => $this->closer->id,
            'admin_user_id_payroll'  => $this->admin->id,
            'status'                 => 'charged',
            'charged'                => 'yes',
        ]);
        $deal->save();

        DealFinancial::create([
            'deal_id'            => $deal->id,
            'fronter_percent'    => 6.00,
            'closer_percent'     => 12.00,
            'admin_percent'      => 2.00,
            'processing_percent' => 3.00,
            'reserve_percent'    => 3.00,
            'marketing_percent'  => 15.00,
            'gross_amount'       => 5000,
            'collected_amount'   => 5000,
            'fronter_commission' => 300.00,
            'closer_commission'  => 600.00,
            'admin_commission'   => 100.00,
            'processing_fee'     => 150.00,
            'reserve_fee'        => 150.00,
            'marketing_cost'     => 750.00,
            'company_net'        => 2950.00,
            'company_net_percent'=> 0.59,
            'is_locked'          => false,
            'is_disputed'        => false,
            'is_reversed'        => false,
            'calculated_at'      => now(),
            'created_by'         => $this->admin->id,
        ]);

        return $deal->fresh();
    }

    private function buildBatchForWeek(Carbon $monday, ?string $forceStatus = null): PayrollBatchV2
    {
        $batch = PayrollBatchBuilder::buildWeeklyBatch($monday->copy(), $monday->copy()->endOfWeek());
        if ($forceStatus !== null) {
            $batch->forceFill(['batch_status' => $forceStatus])->save();
        }
        return $batch;
    }

    // ─────────────────────────────────────────────────────────────────

    public function test_eligible_deal_is_attached_to_a_fresh_batch(): void
    {
        $monday = Carbon::parse('2026-04-13');
        $deal = $this->makeEligibleDeal($monday->copy()->addDays(2));

        $batch = $this->buildBatchForWeek($monday);

        $this->assertDatabaseHas('payroll_batch_deals', [
            'payroll_batch_id' => $batch->id,
            'deal_id'          => $deal->id,
        ]);
    }

    public function test_does_not_re_attach_deal_already_in_paid_batch(): void
    {
        // Regression: this exclusion was already in place pre-2026-05.
        $monday = Carbon::parse('2026-04-13');
        $deal = $this->makeEligibleDeal($monday->copy()->addDays(2));

        $first = $this->buildBatchForWeek($monday, forceStatus: 'paid');
        $this->assertDatabaseCount('payroll_batch_deals', 1);

        $second = $this->buildBatchForWeek($monday);
        $this->assertSame(1, PayrollBatchDeal::where('deal_id', $deal->id)->count(),
            'Deal already in a paid batch must not be attached to a new batch.');
        $this->assertDatabaseMissing('payroll_batch_deals', [
            'payroll_batch_id' => $second->id,
            'deal_id'          => $deal->id,
        ]);
    }

    public function test_does_not_re_attach_deal_already_in_locked_batch(): void
    {
        // Regression: this exclusion was already in place pre-2026-05.
        $monday = Carbon::parse('2026-04-13');
        $deal = $this->makeEligibleDeal($monday->copy()->addDays(2));

        $first = $this->buildBatchForWeek($monday, forceStatus: 'locked');
        $second = $this->buildBatchForWeek($monday);

        $this->assertSame(1, PayrollBatchDeal::where('deal_id', $deal->id)->count());
        $this->assertDatabaseMissing('payroll_batch_deals', [
            'payroll_batch_id' => $second->id,
            'deal_id'          => $deal->id,
        ]);
    }

    public function test_does_not_re_attach_deal_already_in_draft_batch(): void
    {
        // NEW behaviour from 2026-05 broadened-exclusion fix. Without this,
        // a manager could click "Build weekly batch" twice and end up with
        // the same deal in both batches.
        $monday = Carbon::parse('2026-04-13');
        $deal = $this->makeEligibleDeal($monday->copy()->addDays(2));

        $first = $this->buildBatchForWeek($monday); // status defaults to 'draft'
        $this->assertSame('draft', $first->batch_status);

        $second = $this->buildBatchForWeek($monday);

        $this->assertSame(1, PayrollBatchDeal::where('deal_id', $deal->id)->count(),
            'Deal already attached to a draft batch must not be attached to a second batch.');
        $this->assertDatabaseMissing('payroll_batch_deals', [
            'payroll_batch_id' => $second->id,
            'deal_id'          => $deal->id,
        ]);
    }

    public function test_does_not_re_attach_deal_already_in_approved_batch(): void
    {
        // NEW behaviour from 2026-05 broadened-exclusion fix.
        $monday = Carbon::parse('2026-04-13');
        $deal = $this->makeEligibleDeal($monday->copy()->addDays(2));

        $first = $this->buildBatchForWeek($monday, forceStatus: 'approved');
        $second = $this->buildBatchForWeek($monday);

        $this->assertSame(1, PayrollBatchDeal::where('deal_id', $deal->id)->count(),
            'Deal already attached to an approved batch must not be attached to a second batch.');
        $this->assertDatabaseMissing('payroll_batch_deals', [
            'payroll_batch_id' => $second->id,
            'deal_id'          => $deal->id,
        ]);
    }

    public function test_running_buildWeeklyBatch_twice_in_a_row_does_not_double_attach(): void
    {
        // The headline scenario: admin clicks the "Build weekly batch"
        // button twice by accident. Before the fix, the deal would be in
        // both draft batches. After: only the first.
        $monday = Carbon::parse('2026-04-13');
        $deal = $this->makeEligibleDeal($monday->copy()->addDays(2));

        $first  = $this->buildBatchForWeek($monday);
        $second = $this->buildBatchForWeek($monday);

        $this->assertNotSame($first->id, $second->id, 'Two distinct batch records should exist.');
        $this->assertSame(1, PayrollBatchDeal::where('deal_id', $deal->id)->count(),
            'The same deal must not appear in both batches.');
        $this->assertDatabaseHas('payroll_batch_deals', [
            'payroll_batch_id' => $first->id,
            'deal_id'          => $deal->id,
        ]);
        $this->assertDatabaseMissing('payroll_batch_deals', [
            'payroll_batch_id' => $second->id,
            'deal_id'          => $deal->id,
        ]);
    }

    public function test_unique_constraint_blocks_manual_double_insert_into_same_batch(): void
    {
        // DB-level safety net: even if a future bug bypasses the service-
        // level filter, the unique constraint must prevent the same deal
        // landing twice in the same batch row set.
        $monday = Carbon::parse('2026-04-13');
        $deal = $this->makeEligibleDeal($monday->copy()->addDays(2));
        $batch = $this->buildBatchForWeek($monday);

        $financial = DealFinancial::where('deal_id', $deal->id)->firstOrFail();

        $this->expectException(QueryException::class);
        PayrollBatchDeal::create([
            'payroll_batch_id'  => $batch->id,
            'deal_id'           => $deal->id,
            'deal_financial_id' => $financial->id,
        ]);
    }
}
