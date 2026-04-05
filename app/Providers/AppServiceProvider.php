<?php

namespace App\Providers;

use App\Models\ChargebackCase;
use App\Models\CrmNote;
use App\Models\Deal;
use App\Models\VideoRoom;
use App\Policies\ChargebackCasePolicy;
use App\Policies\ClientPolicy;
use App\Policies\CrmNotePolicy;
use App\Policies\VideoRoomPolicy;
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
        // Register policies
        Gate::policy(Deal::class, ClientPolicy::class);
        Gate::policy(CrmNote::class, CrmNotePolicy::class);
        Gate::policy(ChargebackCase::class, ChargebackCasePolicy::class);
        Gate::policy(VideoRoom::class, VideoRoomPolicy::class);
        $defaultConnection = config('database.default');

        // Prevent hard failures when DB_CONNECTION=sqlsrv but SQL Server drivers are not installed.
        if ($defaultConnection === 'sqlsrv' && ! extension_loaded('pdo_sqlsrv')) {
            config(['database.default' => 'mysql']);

            Log::warning('DB_CONNECTION=sqlsrv but pdo_sqlsrv is not installed. Falling back to mysql.');
        }
    }
}
