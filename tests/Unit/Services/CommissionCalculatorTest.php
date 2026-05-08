<?php

namespace Tests\Unit\Services;

use App\Models\Deal;
use App\Models\User;
use App\Services\CommissionCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * First fixtures for the legacy commission engine — none existed before. The
 * audit called out two competing engines (CommissionCalculator vs
 * Services\Payroll\DealPayrollCalculator) with no money-correctness coverage.
 *
 * Important: migration 2026_04_08_100003_seed_payroll_settings_defaults.php
 * renames the old wide-column payroll_settings table to ..._legacy and
 * creates a NEW key/value-shaped payroll_settings. CommissionCalculator
 * still reads `$row->closer_pct` from `payroll_settings` and finds nothing —
 * which silently falls back to its own class constants. So these fixtures
 * are pinned against the FALLBACK constants (which is what production also
 * uses today, after the schema migration). This is itself a finding worth
 * fixing later — UI rate changes don't reach this engine.
 */
class CommissionCalculatorTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_single_us_closer_basic_split_against_fallback_constants(): void
    {
        // Constants: SNR_PCT=3, VD_PCT=5, DEFAULT_CLOSER_PCT=40, FRONTER_PCT=10
        // fee=5000, snr=3% of 5000 = 150, vd=0
        // payable = 5000 - 150 = 4850
        // closer (40%) = 1940
        // fronter (10% of payable) = 485
        $deal = $this->makeDeal();
        CommissionCalculator::calculate($deal);
        $deal->refresh();

        $this->assertEquals(150.00, (float) $deal->snr_deduction);
        $this->assertEquals(0.00,   (float) $deal->vd_deduction);
        $this->assertEquals(1940.00, (float) $deal->closer_comm_amount);
        $this->assertEquals(485.00,  (float) $deal->fronter_comm_amount);
    }

    public function test_vd_deal_takes_extra_deduction_and_lowers_payable(): void
    {
        // fee=5000, snr=150, vd=5%=250
        // payable = 5000 - 150 - 250 = 4600
        // closer (40%) = 1840
        // fronter (10% of 4600) = 460
        $deal = $this->makeDeal(['was_vd' => 'Yes']);
        CommissionCalculator::calculate($deal);
        $deal->refresh();

        $this->assertEquals(150.00, (float) $deal->snr_deduction);
        $this->assertEquals(250.00, (float) $deal->vd_deduction);
        $this->assertEquals(1840.00, (float) $deal->closer_comm_amount);
        $this->assertEquals(460.00,  (float) $deal->fronter_comm_amount);
    }

    public function test_panama_fronter_caps_closer_at_25_pct_and_halves_fronter_payout(): void
    {
        // panama_closer_pct = 25, payable = 4850
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
        // Caller asks for 75% closer; default fallback is 40; should clamp to 40.
        $deal = $this->makeDeal(['closer_comm_pct' => 75]);
        CommissionCalculator::calculate($deal);
        $deal->refresh();

        // payable=4850, capped at 40% → 1940
        $this->assertEquals(40.00, (float) $deal->closer_comm_pct);
        $this->assertEquals(1940.00, (float) $deal->closer_comm_amount);
    }
}
