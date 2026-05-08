<?php

namespace Tests\Feature\Security;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pins the env-gate added to DatabaseSeeder so a stray `db:seed` in production
 * cannot install the documented weak demo passwords (primeadmin/prime2026,
 * mtorres/admin123, front123/456/789, close123/456/789, etc.).
 */
class SeederEnvGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_skips_user_creation_in_production(): void
    {
        $this->app['env'] = 'production';

        $exitCode = Artisan::call('db:seed', [
            '--class' => DatabaseSeeder::class,
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode, 'Seeder must exit cleanly in production, not error.');
        $this->assertSame(0, DB::table('users')->count(),
            'Seeder must not create any demo users in production.');
    }

    public function test_seeder_skips_in_staging_environment(): void
    {
        $this->app['env'] = 'staging';

        Artisan::call('db:seed', [
            '--class' => DatabaseSeeder::class,
            '--force' => true,
        ]);

        $this->assertSame(0, DB::table('users')->count(),
            'Seeder must not create demo users in staging either — only local/testing/development.');
    }

    public function test_seeder_creates_demo_users_in_local_environment(): void
    {
        $this->app['env'] = 'local';

        $exitCode = Artisan::call('db:seed', [
            '--class' => DatabaseSeeder::class,
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertGreaterThan(0, DB::table('users')->count(),
            'Seeder must populate demo users in local environment so dev can log in.');
    }
}
