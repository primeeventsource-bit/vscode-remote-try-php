<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\Lead;
use App\Models\PipelineEvent;
use App\Models\User;
use App\Repositories\StatisticsRepository;
use App\Services\PipelineEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PipelineStatisticsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        return User::create([
            'name' => fake()->name(),
            'email' => fake()->email(),
            'username' => fake()->unique()->userName(),
            'password' => bcrypt('password'),
            'role' => $role,
            'permissions' => ['master_override'],
            'avatar' => 'XX',
            'color' => '#000',
            'status' => 'online',
        ]);
    }

    private function makeLead(array $attrs = []): Lead
    {
        return Lead::create(array_merge([
            'owner_name' => fake()->name(),
            'resort' => 'Test Resort',
            'phone1' => '555-0100',
        ], $attrs));
    }

    private function makeDeal(array $attrs = []): Deal
    {
        return Deal::create(array_merge([
            'owner_name' => 'Test Deal',
            'status' => 'pending_admin',
            'charged' => 'no',
            'charged_back' => 'no',
            'fee' => 5000,
        ], $attrs));
    }

    // ══════════════════════════════════════════════════════════════
    // PIPELINE EVENT LOGGING
    // ══════════════════════════════════════════════════════════════

    public function test_transfer_to_closer_creates_event(): void
    {
        $fronter = $this->makeUser('fronter');
        $closer = $this->makeUser('closer');
        $lead = $this->makeLead(['original_fronter' => $fronter->id]);

        PipelineEventService::logTransferredToCloser($lead, $fronter, $closer);

        $this->assertDatabaseHas('pipeline_events', [
            'lead_id' => $lead->id,
            'event_type' => 'transferred_to_closer',
            'source_user_id' => $fronter->id,
            'target_user_id' => $closer->id,
        ]);
    }

    public function test_closer_closed_deal_creates_event(): void
    {
        $closer = $this->makeUser('closer');
        $lead = $this->makeLead();
        $deal = $this->makeDeal(['closer' => $closer->id, 'lead_id' => $lead->id]);

        PipelineEventService::logCloserClosedDeal($lead, $deal, $closer);

        $this->assertDatabaseHas('pipeline_events', [
            'deal_id' => $deal->id,
            'event_type' => 'closer_closed_deal',
            'performed_by_user_id' => $closer->id,
        ]);
    }

    public function test_sent_to_verification_creates_event(): void
    {
        $closer = $this->makeUser('closer');
        $admin = $this->makeUser('admin');
        $deal = $this->makeDeal(['closer' => $closer->id, 'assigned_admin' => $admin->id]);

        PipelineEventService::logSentToVerification($deal, $closer, $admin);

        $this->assertDatabaseHas('pipeline_events', [
            'deal_id' => $deal->id,
            'event_type' => 'sent_to_verification',
            'source_user_id' => $closer->id,
            'target_user_id' => $admin->id,
        ]);
    }

    public function test_verification_charged_green_creates_event(): void
    {
        $admin = $this->makeUser('admin');
        $deal = $this->makeDeal(['assigned_admin' => $admin->id]);

        PipelineEventService::logVerificationChargedGreen($deal, $admin);

        $this->assertDatabaseHas('pipeline_events', [
            'deal_id' => $deal->id,
            'event_type' => 'verification_charged_green',
            'performed_by_user_id' => $admin->id,
            'success_flag' => true,
        ]);
    }

    public function test_verification_not_charged_creates_event(): void
    {
        $admin = $this->makeUser('admin');
        $deal = $this->makeDeal(['assigned_admin' => $admin->id]);

        PipelineEventService::logVerificationNotCharged($deal, $admin, 'Client cancelled');

        $this->assertDatabaseHas('pipeline_events', [
            'deal_id' => $deal->id,
            'event_type' => 'verification_not_charged',
            'success_flag' => false,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // FRONTER STATS
    // ══════════════════════════════════════════════════════════════

    public function test_fronter_stats_counts_transfers_correctly(): void
    {
        $fronter = $this->makeUser('fronter');
        $closer = $this->makeUser('closer');

        // Create 3 transfers
        for ($i = 0; $i < 3; $i++) {
            $lead = $this->makeLead(['original_fronter' => $fronter->id]);
            PipelineEventService::logTransferredToCloser($lead, $fronter, $closer);
        }

        // Create 2 deals from those leads
        $leads = Lead::where('original_fronter', $fronter->id)->limit(2)->get();
        foreach ($leads as $lead) {
            $deal = $this->makeDeal(['fronter' => $fronter->id, 'closer' => $closer->id, 'lead_id' => $lead->id]);
            PipelineEventService::logCloserClosedDeal($lead, $deal, $closer);
        }

        $stats = StatisticsRepository::getFronterStats();
        $fs = collect($stats)->firstWhere('user_id', $fronter->id);

        $this->assertNotNull($fs);
        $this->assertEquals(3, $fs['transfers_sent']);
        $this->assertEquals(2, $fs['deals_closed']);
        $this->assertEquals(1, $fs['no_deals']);
        $this->assertEqualsWithDelta(66.7, $fs['close_pct'], 0.1);
        $this->assertEqualsWithDelta(33.3, $fs['no_deal_pct'], 0.1);
    }

    // ══════════════════════════════════════════════════════════════
    // CLOSER STATS
    // ══════════════════════════════════════════════════════════════

    public function test_closer_stats_counts_correctly(): void
    {
        $fronter = $this->makeUser('fronter');
        $closer = $this->makeUser('closer');
        $admin = $this->makeUser('admin');

        // 4 transfers received
        for ($i = 0; $i < 4; $i++) {
            $lead = $this->makeLead();
            PipelineEventService::logTransferredToCloser($lead, $fronter, $closer);
        }

        // 3 deals closed
        $leads = Lead::limit(3)->get();
        $deals = [];
        foreach ($leads as $lead) {
            $deal = $this->makeDeal(['closer' => $closer->id, 'lead_id' => $lead->id]);
            PipelineEventService::logCloserClosedDeal($lead, $deal, $closer);
            $deals[] = $deal;
        }

        // 2 sent to verification
        foreach (array_slice($deals, 0, 2) as $deal) {
            PipelineEventService::logSentToVerification($deal, $closer, $admin);
        }

        $stats = StatisticsRepository::getCloserStats();
        $cs = collect($stats)->firstWhere('user_id', $closer->id);

        $this->assertNotNull($cs);
        $this->assertEquals(4, $cs['transfers_received']);
        $this->assertEquals(3, $cs['deals_closed']);
        $this->assertEquals(2, $cs['sent_to_verification']);
        $this->assertEquals(1, $cs['not_closed']);
        $this->assertEquals(75.0, $cs['close_pct']);
        $this->assertEquals(25.0, $cs['no_close_pct']);
        $this->assertEqualsWithDelta(66.7, $cs['verification_pct'], 0.1);
    }

    // ══════════════════════════════════════════════════════════════
    // ADMIN STATS
    // ══════════════════════════════════════════════════════════════

    public function test_admin_stats_counts_correctly(): void
    {
        $closer = $this->makeUser('closer');
        $admin = $this->makeUser('admin');

        // 5 deals sent to this admin
        $deals = [];
        for ($i = 0; $i < 5; $i++) {
            $deal = $this->makeDeal(['closer' => $closer->id, 'assigned_admin' => $admin->id]);
            PipelineEventService::logSentToVerification($deal, $closer, $admin);
            $deals[] = $deal;
        }

        // 3 charged green
        foreach (array_slice($deals, 0, 3) as $deal) {
            PipelineEventService::logVerificationChargedGreen($deal, $admin);
        }

        // 1 not charged
        PipelineEventService::logVerificationNotCharged($deals[3], $admin);

        $stats = StatisticsRepository::getAdminStats();
        $as = collect($stats)->firstWhere('user_id', $admin->id);

        $this->assertNotNull($as);
        $this->assertEquals(5, $as['received']);
        $this->assertEquals(3, $as['charged_green']);
        $this->assertEquals(1, $as['not_charged']);
        $this->assertEquals(60.0, $as['charge_pct']);
        $this->assertEquals(20.0, $as['not_charged_pct']);
    }

    // ══════════════════════════════════════════════════════════════
    // PERCENTAGE SAFETY
    // ══════════════════════════════════════════════════════════════

    public function test_percentages_zero_when_no_data(): void
    {
        $fronter = $this->makeUser('fronter');

        $stats = StatisticsRepository::getFronterStats();
        $fs = collect($stats)->firstWhere('user_id', $fronter->id);

        $this->assertNotNull($fs);
        $this->assertEquals(0, $fs['transfers_sent']);
        $this->assertEquals(0.0, $fs['close_pct']);
        $this->assertEquals(0.0, $fs['no_deal_pct']);
    }

    public function test_overall_summary_zero_safe(): void
    {
        $summary = StatisticsRepository::getOverallSummary();

        $this->assertEquals(0, $summary['total_transfers']);
        $this->assertEquals(0.0, $summary['overall_conversion_pct']);
        $this->assertEquals(0.0, $summary['verification_charge_pct']);
    }

    // ══════════════════════════════════════════════════════════════
    // DATE FILTERING
    // ══════════════════════════════════════════════════════════════

    public function test_date_range_filtering_works(): void
    {
        $fronter = $this->makeUser('fronter');
        $closer = $this->makeUser('closer');

        // Old transfer
        $oldLead = $this->makeLead();
        PipelineEvent::create([
            'lead_id' => $oldLead->id,
            'event_type' => PipelineEvent::TRANSFERRED_TO_CLOSER,
            'source_user_id' => $fronter->id,
            'target_user_id' => $closer->id,
            'source_role' => 'fronter',
            'target_role' => 'closer',
            'event_at' => now()->subMonths(6),
        ]);

        // Recent transfer
        $newLead = $this->makeLead();
        PipelineEventService::logTransferredToCloser($newLead, $fronter, $closer);

        // All time: should see 2
        $allStats = StatisticsRepository::getFronterStats();
        $this->assertEquals(2, collect($allStats)->firstWhere('user_id', $fronter->id)['transfers_sent']);

        // Last 30 days: should see 1
        $recentStats = StatisticsRepository::getFronterStats(now()->subDays(30));
        $this->assertEquals(1, collect($recentStats)->firstWhere('user_id', $fronter->id)['transfers_sent']);
    }

    // ══════════════════════════════════════════════════════════════
    // STATISTICS PAGE LOADS
    // ══════════════════════════════════════════════════════════════

    public function test_statistics_tab_loads_without_500(): void
    {
        $user = $this->makeUser('master_admin');

        Livewire::actingAs($user)
            ->test(\App\Livewire\Statistics::class)
            ->assertStatus(200)
            ->assertSee('Pipeline Summary');
    }

    public function test_statistics_all_tabs_load_with_empty_data(): void
    {
        $user = $this->makeUser('master_admin');

        $component = Livewire::actingAs($user)
            ->test(\App\Livewire\Statistics::class);

        // Each tab must render without crash
        foreach (['summary', 'fronters', 'closers', 'admins'] as $tab) {
            $component->set('tab', $tab)->assertStatus(200);
        }
    }

    public function test_statistics_data_types_are_consistent_arrays(): void
    {
        $this->makeUser('fronter');
        $this->makeUser('closer');
        $this->makeUser('admin');

        $fronterStats = StatisticsRepository::getFronterStats();
        $closerStats = StatisticsRepository::getCloserStats();
        $adminStats = StatisticsRepository::getAdminStats();
        $summary = StatisticsRepository::getOverallSummary();

        // All must be arrays, never objects
        $this->assertIsArray($fronterStats);
        $this->assertIsArray($closerStats);
        $this->assertIsArray($adminStats);
        $this->assertIsArray($summary);

        // Each row must be an array with expected keys
        if (!empty($fronterStats)) {
            $this->assertIsArray($fronterStats[0]);
            $this->assertArrayHasKey('user_id', $fronterStats[0]);
            $this->assertArrayHasKey('transfers_sent', $fronterStats[0]);
            $this->assertArrayHasKey('close_pct', $fronterStats[0]);
        }

        if (!empty($closerStats)) {
            $this->assertIsArray($closerStats[0]);
            $this->assertArrayHasKey('user_id', $closerStats[0]);
            $this->assertArrayHasKey('transfers_received', $closerStats[0]);
            $this->assertArrayHasKey('verification_pct', $closerStats[0]);
        }

        if (!empty($adminStats)) {
            $this->assertIsArray($adminStats[0]);
            $this->assertArrayHasKey('user_id', $adminStats[0]);
            $this->assertArrayHasKey('charged_green', $adminStats[0]);
            $this->assertArrayHasKey('charge_pct', $adminStats[0]);
        }
    }
}
