<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Role permission defaults ────────────────────────────────
        $allPerms = [
            'view_dashboard','view_stats','view_leads','view_pipeline','view_deals',
            'view_verification','view_chat','view_users','import_csv','add_leads',
            'assign_leads','view_all_leads','disposition_leads','create_deals',
            'toggle_charged','toggle_chargeback','upload_files','view_login_info',
            'create_chats','view_payroll','edit_payroll','manage_payroll',
            'edit_users','delete_users','master_override',
        ];

        $adminPerms = array_values(array_filter($allPerms, fn($k) => $k !== 'master_override'));

        $adminLimited = [
            'view_dashboard','view_leads','view_pipeline','view_deals','view_verification',
            'view_chat','view_all_leads','assign_leads','import_csv','add_leads',
            'toggle_charged','toggle_chargeback','view_login_info','create_deals',
            'create_chats','view_payroll',
        ];

        $fronter = ['view_leads','view_pipeline','view_chat','disposition_leads','create_chats','view_payroll'];
        $closer = [
            'view_dashboard','view_leads','view_pipeline','view_deals','view_verification',
            'view_chat','disposition_leads','create_deals','create_chats','view_login_info','view_payroll',
        ];

        // ─── Users ───────────────────────────────────────────────────
        $users = [
            ['name' => 'David Chen',       'email' => 'david@tl.com',  'role' => 'master_admin',  'avatar' => 'DC', 'color' => '#dc2626', 'username' => 'dchen',       'password' => '12345678', 'permissions' => $allPerms],
            ['name' => 'Angela Ross',      'email' => 'angela@tl.com', 'role' => 'master_admin',  'avatar' => 'AR', 'color' => '#b91c1c', 'username' => 'aross',       'password' => '12345678', 'permissions' => $allPerms],
            ['name' => 'Mike Torres',      'email' => 'mike@tl.com',   'role' => 'admin',         'avatar' => 'MT', 'color' => '#3b82f6', 'username' => 'mtorres',     'password' => 'admin123',  'permissions' => $adminPerms],
            ['name' => 'Sarah Chen',       'email' => 'sarah@tl.com',  'role' => 'admin_limited', 'avatar' => 'SC', 'color' => '#10b981', 'username' => 'schen',       'password' => 'admin456',  'permissions' => $adminLimited],
            ['name' => 'James Okafor',     'email' => 'james@tl.com',  'role' => 'fronter',       'avatar' => 'JO', 'color' => '#ec4899', 'username' => 'jokafor',     'password' => 'front123',  'permissions' => $fronter],
            ['name' => 'Dana Kim',         'email' => 'dana@tl.com',   'role' => 'fronter',       'avatar' => 'DK', 'color' => '#f59e0b', 'username' => 'dkim',        'password' => 'front456',  'permissions' => $fronter],
            ['name' => 'Tyler Brooks',     'email' => 'tyler@tl.com',  'role' => 'fronter',       'avatar' => 'TB', 'color' => '#6366f1', 'username' => 'tbrooks',     'password' => 'front789',  'permissions' => $fronter],
            ['name' => 'Marcus Rivera',    'email' => 'marcus@tl.com', 'role' => 'closer',        'avatar' => 'MR', 'color' => '#8b5cf6', 'username' => 'mrivera',     'password' => 'close123',  'permissions' => $closer],
            ['name' => 'Priya Sharma',     'email' => 'priya@tl.com',  'role' => 'closer',        'avatar' => 'PS', 'color' => '#14b8a6', 'username' => 'psharma',     'password' => 'close456',  'permissions' => $closer],
            ['name' => 'Alex Dominguez',   'email' => 'alex@tl.com',   'role' => 'closer',        'avatar' => 'AD', 'color' => '#ef4444', 'username' => 'adominguez',  'password' => 'close789',  'permissions' => $closer],
        ];

        foreach ($users as $u) {
            DB::table('users')->updateOrInsert(
                ['username' => $u['username']],
                [
                    'name'        => $u['name'],
                    'email'       => $u['email'],
                    'role'        => $u['role'],
                    'avatar'      => $u['avatar'],
                    'color'       => $u['color'],
                    'status'      => 'online',
                    'username'    => $u['username'],
                    'password'    => Hash::make($u['password']),
                    'permissions' => json_encode($u['permissions']),
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]
            );
        }

        $this->command->info('Seeded 10 users.');

        // ─── Default payroll settings ────────────────────────────────
        $exists = DB::table('payroll_settings')->count();
        if ($exists === 0) {
            DB::table('payroll_settings')->insert([
                'closer_pct'    => 50.00,
                'fronter_pct'   => 10.00,
                'snr_pct'       => 2.00,
                'vd_pct'        => 3.00,
                'admin_snr_pct' => 2.00,
                'hourly_rate'   => 19.50,
            ]);
            $this->command->info('Seeded default payroll settings.');
        }

        // ─── Sample chats ────────────────────────────────────────────
        $chatExists = DB::table('chats')->count();
        if ($chatExists === 0) {
            DB::table('chats')->insert([
                [
                    'name'       => 'Sales Floor',
                    'type'       => 'channel',
                    'members'    => json_encode(['u0','u1','u2','u3','u4','u5','u6','u7','u8','u9']),
                    'created_by' => 1,
                    'pinned'     => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name'       => 'Closers Only',
                    'type'       => 'group',
                    'members'    => json_encode(['u0','u5','u6','u8']),
                    'created_by' => 1,
                    'pinned'     => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name'       => 'Admin Team',
                    'type'       => 'group',
                    'members'    => json_encode(['u0','u1','u2','u9']),
                    'created_by' => 1,
                    'pinned'     => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
            $this->command->info('Seeded 3 chat channels.');
        }

        $this->command->info('Database seeding complete.');
    }
}
