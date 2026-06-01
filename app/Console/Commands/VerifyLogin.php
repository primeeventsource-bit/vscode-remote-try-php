<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class VerifyLogin extends Command
{
    protected $signature = 'user:verify-login
        {username : Username to check}
        {password : Password to verify}';

    protected $description = 'Diagnose why a username/password fails: row exists? status active? hash matches? Auth::attempt succeeds?';

    public function handle(): int
    {
        $username = $this->argument('username');
        $password = $this->argument('password');

        $this->line('DB:        '.DB::connection()->getDatabaseName());
        $this->line('Host:      '.config('database.connections.'.config('database.default').'.host'));

        $u = User::where('username', $username)->first();
        if (!$u) {
            $this->error("NOT_FOUND: username={$username}");
            $count = DB::table('users')->count();
            $this->line("Total users in DB: {$count}");
            $sample = DB::table('users')->pluck('username')->take(10)->all();
            $this->line('First 10 usernames: '.implode(', ', $sample));
            return self::FAILURE;
        }

        $this->line("Found:     id={$u->id}");
        $this->line("Role:      {$u->role}");
        $this->line("Status:    {$u->status}");
        $this->line("HashStart: ".substr($u->password, 0, 7));
        $this->line("HashCheck: ".(Hash::check($password, $u->password) ? 'OK' : 'FAIL'));
        $this->line("AuthAttempt: ".(Auth::attempt(['username' => $username, 'password' => $password]) ? 'OK' : 'FAIL'));

        return self::SUCCESS;
    }
}
