<?php

namespace Tests\Feature\Security;

use App\Livewire\Users as UsersComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pins the mount() gate added to the Users Livewire component.
 * Without it, any authenticated user could request /users, render the form,
 * and poke at component state — even though the save methods re-check.
 *
 * Note: User::$fillable does NOT include `role` or `permissions` (model-level
 * decision documented inside User.php) — they must be set explicitly after
 * construction. Tests that omit this end up with a NULL role and silently
 * fail authorization regardless of which role string was "assigned".
 */
class UsersMountGateTest extends TestCase
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

    public function test_fronter_cannot_mount_users_page(): void
    {
        Livewire::actingAs($this->makeUser('fronter'))
            ->test(UsersComponent::class)
            ->assertStatus(403);
    }

    public function test_closer_cannot_mount_users_page(): void
    {
        Livewire::actingAs($this->makeUser('closer'))
            ->test(UsersComponent::class)
            ->assertStatus(403);
    }

    public function test_fronter_panama_cannot_mount_users_page(): void
    {
        Livewire::actingAs($this->makeUser('fronter_panama'))
            ->test(UsersComponent::class)
            ->assertStatus(403);
    }

    public function test_admin_can_mount_users_page(): void
    {
        Livewire::actingAs($this->makeUser('admin'))
            ->test(UsersComponent::class)
            ->assertOk();
    }

    public function test_master_admin_can_mount_users_page(): void
    {
        Livewire::actingAs($this->makeUser('master_admin'))
            ->test(UsersComponent::class)
            ->assertOk();
    }
}
