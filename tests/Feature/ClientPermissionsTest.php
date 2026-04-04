<?php

namespace Tests\Feature;

use App\Livewire\Clients;
use App\Models\ClientAuditLog;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class ClientPermissionsTest extends TestCase
{
    use RefreshDatabase;

    // ── Helper factories ────────────────────────────────────────

    private function createUser(string $role, array $permissions = []): User
    {
        return User::create([
            'name' => fake()->name(),
            'email' => fake()->email(),
            'username' => fake()->unique()->userName(),
            'password' => bcrypt('password'),
            'role' => $role,
            'permissions' => $permissions,
            'avatar' => 'XX',
            'color' => '#000000',
            'status' => 'online',
        ]);
    }

    private function createChargedDeal(array $overrides = []): Deal
    {
        return Deal::create(array_merge([
            'owner_name' => 'Test Client',
            'email' => 'test@example.com',
            'primary_phone' => '555-0100',
            'mailing_address' => '123 Test St',
            'city_state_zip' => 'Test City, TS 12345',
            'resort_name' => 'Test Resort',
            'resort_city_state' => 'Test City, TS',
            'fee' => 5000.00,
            'status' => 'charged',
            'charged' => 'yes',
            'bank' => 'Test Bank',
            'bank2' => 'Test Bank 2',
            'billing_address' => '456 Billing Ave',
            'name_on_card' => 'Test Cardholder',
            'card_type' => 'Visa',
            'card_brand' => 'Visa',
            'card_last4' => '4242',
            'exp_date' => '12/27',
            'notes' => 'Test notes',
        ], $overrides));
    }

    // ══════════════════════════════════════════════════════════════
    // MASTER ADMIN TESTS
    // ══════════════════════════════════════════════════════════════

    public function test_master_admin_can_view_client_record(): void
    {
        $user = $this->createUser('master_admin', ['master_override']);
        $deal = $this->createChargedDeal();

        $this->actingAs($user);
        $this->assertTrue(Gate::allows('view', $deal));
    }

    public function test_master_admin_can_edit_client_info(): void
    {
        $user = $this->createUser('master_admin', ['master_override']);
        $deal = $this->createChargedDeal();

        Livewire::actingAs($user)
            ->test(Clients::class)
            ->call('selectClient', $deal->id)
            ->set('clientForm.owner_name', 'Updated Client Name')
            ->set('clientForm.email', 'updated@example.com')
            ->call('startEditing')
            ->call('saveSection');

        $this->assertDatabaseHas('deals', [
            'id' => $deal->id,
            'owner_name' => 'Updated Client Name',
            'email' => 'updated@example.com',
        ]);
    }

    public function test_master_admin_can_edit_deal_sheet(): void
    {
        $user = $this->createUser('master_admin', ['master_override']);
        $deal = $this->createChargedDeal();

        Livewire::actingAs($user)
            ->test(Clients::class)
            ->call('selectClient', $deal->id)
            ->call('setTab', 'deal_sheet')
            ->call('startEditing')
            ->set('dealSheetForm.fee', 7500.00)
            ->set('dealSheetForm.resort_name', 'Updated Resort')
            ->call('saveSection');

        $deal->refresh();
        $this->assertEquals(7500.00, (float) $deal->fee);
        $this->assertEquals('Updated Resort', $deal->resort_name);
    }

    public function test_master_admin_can_edit_banking_info(): void
    {
        $user = $this->createUser('master_admin', ['master_override']);
        $deal = $this->createChargedDeal();

        Livewire::actingAs($user)
            ->test(Clients::class)
            ->call('selectClient', $deal->id)
            ->call('setTab', 'banking')
            ->call('startEditing')
            ->set('bankingForm.bank', 'Updated Bank Name')
            ->call('saveSection');

        $this->assertDatabaseHas('deals', [
            'id' => $deal->id,
            'bank' => 'Updated Bank Name',
        ]);
    }

    public function test_master_admin_can_edit_payment_profile(): void
    {
        $user = $this->createUser('master_admin', ['master_override']);
        $deal = $this->createChargedDeal();

        Livewire::actingAs($user)
            ->test(Clients::class)
            ->call('selectClient', $deal->id)
            ->call('setTab', 'payment')
            ->call('startEditing')
            ->set('paymentForm.name_on_card', 'Updated Cardholder')
            ->call('saveSection');

        $this->assertDatabaseHas('deals', [
            'id' => $deal->id,
            'name_on_card' => 'Updated Cardholder',
        ]);
    }

    public function test_master_admin_can_view_audit_logs(): void
    {
        $user = $this->createUser('master_admin', ['master_override']);
        $deal = $this->createChargedDeal();

        $this->actingAs($user);
        $this->assertTrue(Gate::allows('viewAuditLogs', $deal));
    }

    // ══════════════════════════════════════════════════════════════
    // ADMIN TESTS
    // ══════════════════════════════════════════════════════════════

    public function test_admin_can_edit_client_info(): void
    {
        $admin = $this->createUser('admin', ['clients.view', 'clients.edit']);
        $deal = $this->createChargedDeal();

        Livewire::actingAs($admin)
            ->test(Clients::class)
            ->call('selectClient', $deal->id)
            ->call('startEditing')
            ->set('clientForm.owner_name', 'Admin Updated Name')
            ->call('saveSection');

        $this->assertDatabaseHas('deals', [
            'id' => $deal->id,
            'owner_name' => 'Admin Updated Name',
        ]);
    }

    public function test_admin_can_edit_deal_sheet(): void
    {
        $admin = $this->createUser('admin', ['clients.view', 'clients.view_deal_sheet', 'clients.edit_deal_sheet']);
        $deal = $this->createChargedDeal();

        Livewire::actingAs($admin)
            ->test(Clients::class)
            ->call('selectClient', $deal->id)
            ->call('setTab', 'deal_sheet')
            ->call('startEditing')
            ->set('dealSheetForm.resort_name', 'Admin Updated Resort')
            ->call('saveSection');

        $this->assertDatabaseHas('deals', [
            'id' => $deal->id,
            'resort_name' => 'Admin Updated Resort',
        ]);
    }

    public function test_admin_can_edit_banking_info(): void
    {
        $admin = $this->createUser('admin', ['clients.view', 'clients.view_banking', 'clients.edit_banking']);
        $deal = $this->createChargedDeal();

        Livewire::actingAs($admin)
            ->test(Clients::class)
            ->call('selectClient', $deal->id)
            ->call('setTab', 'banking')
            ->call('startEditing')
            ->set('bankingForm.bank', 'Admin Updated Bank')
            ->call('saveSection');

        $this->assertDatabaseHas('deals', [
            'id' => $deal->id,
            'bank' => 'Admin Updated Bank',
        ]);
    }

    public function test_admin_cannot_edit_sensitive_financial_without_permission(): void
    {
        $admin = $this->createUser('admin', ['clients.view', 'clients.view_payment_profile']);
        $deal = $this->createChargedDeal();

        $this->actingAs($admin);
        // Admin role alone does not grant sensitive financial
        $this->assertFalse(Gate::allows('editSensitiveFinancial', $deal));
    }

    // ══════════════════════════════════════════════════════════════
    // AGENT / STANDARD USER TESTS
    // ══════════════════════════════════════════════════════════════

    public function test_agent_cannot_edit_banking_info(): void
    {
        $agent = $this->createUser('fronter', ['clients.view']);
        $deal = $this->createChargedDeal(['fronter' => $agent->id]);

        $this->actingAs($agent);
        $this->assertFalse(Gate::allows('editBanking', $deal));
    }

    public function test_agent_cannot_access_sensitive_financial(): void
    {
        $agent = $this->createUser('closer', ['clients.view']);
        $deal = $this->createChargedDeal(['closer' => $agent->id]);

        $this->actingAs($agent);
        $this->assertFalse(Gate::allows('viewSensitiveFinancial', $deal));
        $this->assertFalse(Gate::allows('editSensitiveFinancial', $deal));
    }

    public function test_agent_cannot_edit_deal_sheet_without_permission(): void
    {
        $agent = $this->createUser('fronter', ['clients.view']);
        $deal = $this->createChargedDeal(['fronter' => $agent->id]);

        $this->actingAs($agent);
        $this->assertFalse(Gate::allows('editDealSheet', $deal));
    }

    public function test_agent_cannot_bypass_via_livewire_payload(): void
    {
        $agent = $this->createUser('fronter', ['clients.view']);
        $deal = $this->createChargedDeal(['fronter' => $agent->id]);

        // Agent tries to directly call saveSection on banking tab
        Livewire::actingAs($agent)
            ->test(Clients::class)
            ->call('selectClient', $deal->id)
            ->set('activeTab', 'banking')
            ->set('bankingForm.bank', 'Hacked Bank Name')
            ->call('saveSection');

        // Bank should NOT have changed
        $deal->refresh();
        $this->assertEquals('Test Bank', $deal->bank);
    }

    // ══════════════════════════════════════════════════════════════
    // PERSISTENCE TESTS
    // ══════════════════════════════════════════════════════════════

    public function test_edits_persist_after_refresh(): void
    {
        $user = $this->createUser('master_admin', ['master_override']);
        $deal = $this->createChargedDeal();

        Livewire::actingAs($user)
            ->test(Clients::class)
            ->call('selectClient', $deal->id)
            ->call('startEditing')
            ->set('clientForm.owner_name', 'Persisted Name')
            ->set('clientForm.email', 'persisted@example.com')
            ->call('saveSection');

        // Simulate page refresh by re-querying from DB
        $freshDeal = Deal::find($deal->id);
        $this->assertEquals('Persisted Name', $freshDeal->owner_name);
        $this->assertEquals('persisted@example.com', $freshDeal->email);
    }

    public function test_deal_sheet_edits_persist(): void
    {
        $user = $this->createUser('master_admin', ['master_override']);
        $deal = $this->createChargedDeal();

        Livewire::actingAs($user)
            ->test(Clients::class)
            ->call('selectClient', $deal->id)
            ->call('setTab', 'deal_sheet')
            ->call('startEditing')
            ->set('dealSheetForm.weeks', '4')
            ->set('dealSheetForm.bed_bath', '2/2')
            ->call('saveSection');

        $freshDeal = Deal::find($deal->id);
        $this->assertEquals('4', $freshDeal->weeks);
        $this->assertEquals('2/2', $freshDeal->bed_bath);
    }

    public function test_banking_edits_persist(): void
    {
        $user = $this->createUser('master_admin', ['master_override']);
        $deal = $this->createChargedDeal();

        Livewire::actingAs($user)
            ->test(Clients::class)
            ->call('selectClient', $deal->id)
            ->call('setTab', 'banking')
            ->call('startEditing')
            ->set('bankingForm.bank', 'Persisted Bank')
            ->set('bankingForm.billing_address', '789 New Billing St')
            ->call('saveSection');

        $freshDeal = Deal::find($deal->id);
        $this->assertEquals('Persisted Bank', $freshDeal->bank);
        $this->assertEquals('789 New Billing St', $freshDeal->billing_address);
    }

    public function test_payment_profile_edits_persist(): void
    {
        $user = $this->createUser('master_admin', ['master_override']);
        $deal = $this->createChargedDeal();

        Livewire::actingAs($user)
            ->test(Clients::class)
            ->call('selectClient', $deal->id)
            ->call('setTab', 'payment')
            ->call('startEditing')
            ->set('paymentForm.name_on_card', 'New Cardholder Name')
            ->call('saveSection');

        $freshDeal = Deal::find($deal->id);
        $this->assertEquals('New Cardholder Name', $freshDeal->name_on_card);
    }

    // ══════════════════════════════════════════════════════════════
    // AUDIT LOGGING TESTS
    // ══════════════════════════════════════════════════════════════

    public function test_audit_log_created_on_edit(): void
    {
        $user = $this->createUser('master_admin', ['master_override']);
        $deal = $this->createChargedDeal();

        Livewire::actingAs($user)
            ->test(Clients::class)
            ->call('selectClient', $deal->id)
            ->call('startEditing')
            ->set('clientForm.owner_name', 'Audited Name Change')
            ->call('saveSection');

        $log = ClientAuditLog::where('deal_id', $deal->id)
            ->where('action', 'edited_client_info')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($user->id, $log->user_id);
        $this->assertEquals('master_admin', $log->user_role);
        $this->assertContains('owner_name', $log->changed_fields);
        $this->assertEquals('Test Client', $log->before_values['owner_name']);
        $this->assertEquals('Audited Name Change', $log->after_values['owner_name']);
    }

    public function test_audit_log_created_for_sensitive_section_view(): void
    {
        $user = $this->createUser('master_admin', ['master_override']);
        $deal = $this->createChargedDeal();

        Livewire::actingAs($user)
            ->test(Clients::class)
            ->call('selectClient', $deal->id)
            ->call('setTab', 'banking');

        $log = ClientAuditLog::where('deal_id', $deal->id)
            ->where('action', 'viewed_banking')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($user->id, $log->user_id);
    }

    public function test_audit_log_masks_sensitive_fields(): void
    {
        $user = $this->createUser('master_admin', ['master_override']);
        $deal = $this->createChargedDeal();

        Livewire::actingAs($user)
            ->test(Clients::class)
            ->call('selectClient', $deal->id)
            ->call('setTab', 'payment')
            ->call('startEditing')
            ->set('paymentForm.exp_date', '01/28')
            ->call('saveSection');

        $log = ClientAuditLog::where('deal_id', $deal->id)
            ->where('action', 'edited_payment_profile')
            ->first();

        $this->assertNotNull($log);
        // exp_date should be masked in audit log
        if (isset($log->before_values['exp_date'])) {
            $this->assertEquals('***MASKED***', $log->before_values['exp_date']);
        }
    }

    // ══════════════════════════════════════════════════════════════
    // CARD MASKING TESTS
    // ══════════════════════════════════════════════════════════════

    public function test_masked_card_display_works(): void
    {
        $deal = $this->createChargedDeal([
            'card_last4' => '4242',
            'card_brand' => 'Visa',
        ]);

        $this->assertEquals('Visa ****4242', $deal->masked_card);
    }

    public function test_card_numbers_hidden_from_serialization(): void
    {
        $deal = $this->createChargedDeal();

        $array = $deal->toArray();
        $this->assertArrayNotHasKey('card_number', $array);
        $this->assertArrayNotHasKey('card_number2', $array);
        $this->assertArrayNotHasKey('cv2', $array);
        $this->assertArrayNotHasKey('cv2_2', $array);
    }

    public function test_cvv_is_never_stored(): void
    {
        // CVV fields are not in $fillable, so mass assignment should ignore them
        $deal = Deal::create([
            'owner_name' => 'CVV Test Client',
            'status' => 'charged',
            'charged' => 'yes',
            'fee' => 1000,
            'cv2' => '123',  // should be ignored
            'cv2_2' => '456', // should be ignored
        ]);

        $raw = \DB::table('deals')->where('id', $deal->id)->first();
        $this->assertNull($raw->cv2);
        $this->assertNull($raw->cv2_2);
    }

    // ══════════════════════════════════════════════════════════════
    // ROUTE / ACCESS TESTS
    // ══════════════════════════════════════════════════════════════

    public function test_unauthenticated_user_cannot_access_clients(): void
    {
        $response = $this->get('/clients');
        $response->assertRedirect('/login');
    }

    public function test_agent_sees_only_own_clients(): void
    {
        $agent = $this->createUser('closer', ['clients.view', 'view_deals']);
        $otherUser = $this->createUser('closer', ['clients.view', 'view_deals']);

        $ownDeal = $this->createChargedDeal(['closer' => $agent->id]);
        $otherDeal = $this->createChargedDeal(['closer' => $otherUser->id, 'owner_name' => 'Other Client']);

        $component = Livewire::actingAs($agent)
            ->test(Clients::class);

        // The rendered output should contain own client but not other
        $component->assertSee('Test Client')
            ->assertDontSee('Other Client');
    }
}
