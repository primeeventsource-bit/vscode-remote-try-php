<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the role middleware added to POST/PUT /api/deals.
 * Before the fix, any authenticated user (incl. fronters with no create_deals
 * permission) could create or mutate deals over the JSON API.
 *
 * Note: User::$fillable excludes `role`/`permissions` — set explicitly.
 */
class DealApiAuthzTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        $u = User::create([
            'name'     => "Test {$role}",
            'email'    => "{$role}@test.example",
            'username' => "u_{$role}",
            'password' => bcrypt('password'),
            'avatar'   => 'XX',
            'color'    => '#000000',
            'status'   => 'online',
        ]);
        $u->role = $role;
        $u->permissions = [];
        $u->save();
        return $u->fresh();
    }

    public function test_post_deals_returns_403_for_fronter(): void
    {
        $this->actingAs($this->makeUser('fronter'))
             ->postJson('/api/deals', ['owner_name' => 'X'])
             ->assertStatus(403);
    }

    public function test_put_deals_returns_403_for_fronter(): void
    {
        $this->actingAs($this->makeUser('fronter'))
             ->putJson('/api/deals/999', ['owner_name' => 'X'])
             ->assertStatus(403);
    }

    public function test_post_deals_returns_403_for_agent(): void
    {
        // 'agent' is in the seeder's role list but not in the create_deals gate.
        $this->actingAs($this->makeUser('agent'))
             ->postJson('/api/deals', ['owner_name' => 'X'])
             ->assertStatus(403);
    }

    public function test_post_deals_passes_role_gate_for_closer(): void
    {
        $response = $this->actingAs($this->makeUser('closer'))
            ->postJson('/api/deals', ['owner_name' => 'Closer Customer']);

        $this->assertNotSame(403, $response->status(),
            'Closer must pass the role gate on POST /api/deals (closers have create_deals).');
    }

    public function test_post_deals_passes_role_gate_for_admin(): void
    {
        $response = $this->actingAs($this->makeUser('admin'))
            ->postJson('/api/deals', ['owner_name' => 'Admin Customer']);

        $this->assertNotSame(403, $response->status(),
            'Admin must pass the role gate on POST /api/deals.');
    }

    public function test_put_deals_passes_role_gate_for_admin(): void
    {
        // Returns 404 (deal does not exist) — the point is it's NOT 403.
        $response = $this->actingAs($this->makeUser('admin'))
            ->putJson('/api/deals/999', ['owner_name' => 'X']);

        $this->assertNotSame(403, $response->status());
    }
}
