<?php

namespace App\Providers;

use App\Models\Deal;
use App\Policies\ClientPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Register the ClientPolicy for Deal model (clients are charged deals)
        Gate::policy(Deal::class, ClientPolicy::class);
        $defaultConnection = config('database.default');

        // Prevent hard failures when DB_CONNECTION=sqlsrv but SQL Server drivers are not installed.
        if ($defaultConnection === 'sqlsrv' && ! extension_loaded('pdo_sqlsrv')) {
            config(['database.default' => 'mysql']);

            Log::warning('DB_CONNECTION=sqlsrv but pdo_sqlsrv is not installed. Falling back to mysql.');
        }
    }
}
