<?php

namespace Tests\Unit\Services;

use App\Models\Deal;
use App\Models\PayrollSettingModel;
use App\Models\User;
use App\Services\CommissionCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fixtures for the legacy commission engine.
 *
 * As of 2026-05 this engine reads commission percentages from the same
 * PayrollSettingModel store as DealPayrollCalculator (single source of
 * truth for UI-configurable rates). SNR / VD / Panama / multi-closer
 * remain class constants with no UI knob.
 *
 * Each test seeds explicit rates in setUp() rather than relying on whatever
 * migration 2026_04_08_100003 happens to seed — pinning the math to the
 * rates the test cares about.
 */
class CommissionCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Pin closer/fronter to the legacy fallback constants so the math
        // assertions below stay stable regardless of what migration 100003
        // happens to seed today.
        PayrollSettingModel::set('closer_default_percent',  '40.00', 'decimal');
        PayrollSettingModel::set('fronter_default_percent', '10.00', 'decimal');
    }

    private function makeDeal(array $overrides = []): Deal
    {
        $deal = new Deal();
        $deal->forceFill(array_merge([
            'owner_name' => 'Fixture Customer',
            'fee'        => 5000,
            'is_vd_deal' => false,
            'was_vd'     => 'No',
            'status'     => 'open',
        ], $overrides));
        $deal->save();
        return $deal->fresh();
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

    public function test_zero_fee_returns_unchanged_without_saving_commissions(): void
    {
        $deal = $this->makeDeal(['fee' => 0]);
        $result = CommissionCalculator::calculate($deal);

        // Early return path — no commission fields set.
        $this->assertEquals(0.0, (float) ($result->closer_comm_amount ?? 0));
        $this->assertEquals(0.0, (float) ($result->fronter_comm_amount ?? 0));
    }

    public function test_single_us_closer_basic_split(): void
    {
        // Constants: SNR_PCT=3, VD_PCT=5; settings: closer=40, fronter=10
        // fee=5000, snr=3% of 5000 = 150, vd=0
        // payable = 5000 - 150 = 4850
        // closer (40%) = 1940, fronter (10% of payable) = 485
        $deal = $this->makeDeal();
        CommissionCalculator::calculate($deal);
        $deal->refresh();

        $this->assertEquals(150.00,  (float) $deal->snr_deduction);
        $this->assertEquals(0.00,    (float) $deal->vd_deduction);
        $this->assertEquals(1940.00, (float) $deal->closer_comm_amount);
        $this->assertEquals(485.00,  (float) $deal->fronter_comm_amount);
    }

    public function test_vd_deal_takes_extra_deduction_and_lowers_payable(): void
    {
        // fee=5000, snr=150, vd=5%=250
        // payable = 5000 - 150 - 250 = 4600
        // closer (40%) = 1840, fronter (10%) = 460
        $deal = $this->makeDeal(['was_vd' => 'Yes']);
        CommissionCalculator::calculate($deal);
        $deal->refresh();

        $this->assertEquals(150.00,  (float) $deal->snr_deduction);
        $this->assertEquals(250.00,  (float) $deal->vd_deduction);
        $this->assertEquals(1840.00, (float) $deal->closer_comm_amount);
        $this->assertEquals(460.00,  (float) $deal->fronter_comm_amount);
    }

    public function test_panama_fronter_caps_closer_at_25_pct_and_halves_fronter_payout(): void
    {
        // panama_closer_pct = 25 (constant); payable = 4850
        // closer (25%) = 1212.50
        // fronter (10% of payable/2) = 10% of 2425 = 242.50
        $panamaFronter = $this->makeUser('fronter_panama');
        $deal = $this->makeDeal(['fee' => 5000, 'fronter' => $panamaFronter->id]);
        CommissionCalculator::calculate($deal);
        $deal->refresh();

        $this->assertEquals(1212.50, (float) $deal->closer_comm_amount);
        $this->assertEquals(242.50,  (float) $deal->fronter_comm_amount);
    }

    public function test_explicit_closer_pct_above_default_is_clamped_down(): void
    {
        // Caller asks for 75% closer; default (from settings) is 40; clamps to 40.
        $deal = $this->makeDeal(['closer_comm_pct' => 75]);
        CommissionCalculator::calculate($deal);
        $deal->refresh();

        // payable=4850, capped at 40% → 1940
        $this->assertEquals(40.00,   (float) $deal->closer_comm_pct);
        $this->assertEquals(1940.00, (float) $deal->closer_comm_amount);
    }

    // ─── Rate-source consolidation tests ────────────────────────────

    public function test_ui_rate_change_propagates_to_calculator(): void
    {
        // The whole point of the 2026-05 consolidation: changing the rate
        // through PayrollSettingModel (the same place the Payroll Settings
        // UI writes) must change what this engine computes.
        PayrollSettingModel::set('closer_default_percent',  '50.00', 'decimal');
        PayrollSettingModel::set('fronter_default_percent', '15.00', 'decimal');

        $deal = $this->makeDeal();
        CommissionCalculator::calculate($deal);
        $deal->refresh();

        // payable = 4850
        // closer (50%) = 2425
        // fronter (15% of 4850) = 727.50
        $this->assertEquals(2425.00, (float) $deal->closer_comm_amount);
        $this->assertEquals(727.50,  (float) $deal->fronter_comm_amount);
    }

    public function test_falls_back_to_class_constants_when_no_setting_row(): void
    {
        // Simulate "no PayrollSettingModel row at all" by clearing the
        // ones setUp() seeded.
        PayrollSettingModel::where('setting_key', 'closer_default_percent')->delete();
        PayrollSettingModel::where('setting_key', 'fronter_default_percent')->delete();

        $deal = $this->makeDeal();
        CommissionCalculator::calculate($deal);
        $deal->refresh();

        // Falls back to constants: closer=40, fronter=10
        // payable = 4850, closer (40%) = 1940, fronter (10%) = 485
        $this->assertEquals(1940.00, (float) $deal->closer_comm_amount);
        $this->assertEquals(485.00,  (float) $deal->fronter_comm_amount);
    }
}
