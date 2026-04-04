<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\PipelineEvent;
use App\Models\User;
use App\Repositories\StatisticsRepository;
use App\Services\PipelineEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardRoleStatsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, array $perms = []): User
    {
        return User::create([
            'name' => fake()->name(),
            'email' => fake()->email(),
            'username' => fake()->unique()->userName(),
            'password' => bcrypt('password'),
            'role' => $role,
            'permissions' => $perms,
            'avatar' => 'XX',
            'color' => '#000',
            'status' => 'online',
        ]);
    }

    private function makeLead(): Lead
    {
        return Lead::create(['owner_name' => fake()->name(), 'resort' => 'Test', 'phone1' => '555-0100']);
    }

    private function makeDeal(array $attrs = []): Deal
    {
        return Deal::create(array_merge([
            'owner_name' => 'Test', 'status' => 'pending_admin', 'charged' => 'no',
            'charged_back' => 'no', 'fee' => 5000,
        ], $attrs));
    }

    // ══════════════════════════════════════════════════════════════
    // FRONTER sees only their own stats
    // ══════════════════════════════════════════════════════════════

    public function test_fronter_only_sees_own_pipeline_stats(): void
    {
        $fronterA = $this->makeUser('fronter');
        $fronterB = $this->makeUser('fronter');
        $closer = $this->makeUser('closer');

        // FronterA: 3 transfers
        for ($i = 0; $i < 3; $i++) {
            $lead = $this->makeLead();
            PipelineEventService::logTransferredToCloser($lead, $fronterA, $closer);
        }

        // FronterB: 5 transfers
        for ($i = 0; $i < 5; $i++) {
            $lead = $this->makeLead();
            PipelineEventService::logTransferredToCloser($lead, $fronterB, $closer);
        }

        // FronterA should see 3, not 8
        $statsA = StatisticsRepository::getFronterDashboardStatsForUser($fronterA);
        $this->assertEquals(3, $statsA['transfers_sent']);

        // FronterB should see 5, not 8
        $statsB = StatisticsRepository::getFronterDashboardStatsForUser($fronterB);
        $this->assertEquals(5, $statsB['transfers_sent']);
    }

    public function test_fronter_dashboard_shows_only_own_data(): void
    {
        $fronter = $this->makeUser('fronter');
        $closer = $this->makeUser('closer');

        $lead = $this->makeLead();
        PipelineEventService::logTransferredToCloser($lead, $fronter, $closer);

        Livewire::actingAs($fronter)
            ->test(Dashboard::class)
            ->assertSee('My Transfers Sent')
            ->assertSee('My Pipeline Performance')
            ->assertDontSee('Company Pipeline Summary')
            ->assertDontSee('Top Closers');
    }

    // ══════════════════════════════════════════════════════════════
    // CLOSER sees only their own stats
    // ══════════════════════════════════════════════════════════════

    public function test_closer_only_sees_own_pipeline_stats(): void
    {
        $fronter = $this->makeUser('fronter');
        $closerA = $this->makeUser('closer');
        $closerB = $this->makeUser('closer');

        // CloserA: receives 2 transfers
        for ($i = 0; $i < 2; $i++) {
            $lead = $this->makeLead();
            PipelineEventService::logTransferredToCloser($lead, $fronter, $closerA);
        }

        // CloserB: receives 4 transfers
        for ($i = 0; $i < 4; $i++) {
            $lead = $this->makeLead();
            PipelineEventService::logTransferredToCloser($lead, $fronter, $closerB);
        }

        $statsA = StatisticsRepository::getCloserDashboardStatsForUser($closerA);
        $this->assertEquals(2, $statsA['transfers_received']);

        $statsB = StatisticsRepository::getCloserDashboardStatsForUser($closerB);
        $this->assertEquals(4, $statsB['transfers_received']);
    }

    public function test_closer_dashboard_shows_only_own_data(): void
    {
        $closer = $this->makeUser('closer');

        Livewire::actingAs($closer)
            ->test(Dashboard::class)
            ->assertSee('My Transfers Received')
            ->assertSee('My Pipeline Performance')
            ->assertDontSee('Company Pipeline Summary')
            ->assertDontSee('Top Closers');
    }

    // ══════════════════════════════════════════════════════════════
    // ADMIN sees only their own stats
    // ══════════════════════════════════════════════════════════════

    public function test_admin_only_sees_own_pipeline_stats(): void
    {
        $closer = $this->makeUser('closer');
        $adminA = $this->makeUser('admin');
        $adminB = $this->makeUser('admin');

        // AdminA: receives 3 deals
        for ($i = 0; $i < 3; $i++) {
            $deal = $this->makeDeal(['closer' => $closer->id, 'assigned_admin' => $adminA->id]);
            PipelineEventService::logSentToVerification($deal, $closer, $adminA);
        }

        // AdminB: receives 1 deal
        $deal = $this->makeDeal(['closer' => $closer->id, 'assigned_admin' => $adminB->id]);
        PipelineEventService::logSentToVerification($deal, $closer, $adminB);

        $statsA = StatisticsRepository::getAdminDashboardStatsForUser($adminA);
        $this->assertEquals(3, $statsA['received']);

        $statsB = StatisticsRepository::getAdminDashboardStatsForUser($adminB);
        $this->assertEquals(1, $statsB['received']);
    }

    public function test_admin_dashboard_shows_only_own_data(): void
    {
        $admin = $this->makeUser('admin');

        Livewire::actingAs($admin)
            ->test(Dashboard::class)
            ->assertSee('My Verification Performance')
            ->assertSee('My Received')
            ->assertDontSee('Company Pipeline Summary');
    }

    // ══════════════════════════════════════════════════════════════
    // MASTER ADMIN sees everything
    // ══════════════════════════════════════════════════════════════

    public function test_master_admin_sees_company_wide_stats(): void
    {
        $master = $this->makeUser('master_admin', ['master_override']);

        Livewire::actingAs($master)
            ->test(Dashboard::class)
            ->assertSee('Company Pipeline Summary')
            ->assertSee('Total Transfers')
            ->assertDontSee('My Pipeline Performance');
    }

    // ══════════════════════════════════════════════════════════════
    // DROPDOWN FILTER works per user
    // ══════════════════════════════════════════════════════════════

    public function test_stats_range_dropdown_updates_for_user(): void
    {
        $fronter = $this->makeUser('fronter');

        $component = Livewire::actingAs($fronter)
            ->test(Dashboard::class);

        // Switch to daily
        $component->set('statsRange', 'daily')->assertStatus(200);
        // Switch to weekly
        $component->set('statsRange', 'weekly')->assertStatus(200);
        // Switch to monthly
        $component->set('statsRange', 'monthly')->assertStatus(200);
        // Back to live
        $component->set('statsRange', 'live')->assertStatus(200);
    }

    // ══════════════════════════════════════════════════════════════
    // NO 500 ERRORS
    // ══════════════════════════════════════════════════════════════

    public function test_dashboard_loads_for_all_roles_with_empty_data(): void
    {
        foreach (['fronter', 'closer', 'admin', 'master_admin'] as $role) {
            $user = $this->makeUser($role, $role === 'master_admin' ? ['master_override'] : []);
            Livewire::actingAs($user)->test(Dashboard::class)->assertStatus(200);
        }
    }
}
