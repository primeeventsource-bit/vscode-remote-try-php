# AGENTS.md — Prime CRM project guide

> Last verified against the codebase: **2026-05-08**.
> If you change this file, also bump the date.

## Project Overview
Prime CRM is a Laravel 11 + Livewire 3 vertical sales-floor / payroll / chargeback application (PHP 8.2). Stack: Blade + Livewire 3 + Alpine for UI, Tailwind CSS for styling, a small REST API with custom bearer-token auth. Despite the name, **it is not a generic CRM peer of HubSpot/Salesforce** — there is no `Contact`, `Account`, `Company`, or `Opportunity` model. The domain is leads → deals → chargebacks/merchants/payroll for a single tenant.

## Stack (verified)
- **PHP**: 8.2+ (composer.json platform pin: `8.4.19`)
- **Backend**: `laravel/framework: ^11.0`, Eloquent
- **Frontend**: `livewire/livewire: ^3.0`, Blade, Tailwind 3.4 (Tailwind CLI, not Vite for prod CSS)
- **Queue**: Laravel Queue (`sync` in dev, `database` or `redis` in prod — see `PRODUCTION_DEPLOY.md`)
- **Auth (web)**: Laravel session + cookies + CSRF
- **Auth (API)**: custom hashed bearer token (`api_tokens` via `App\Http\Middleware\AuthenticateApiToken`) — **not Sanctum**
- **Comms**: `twilio/sdk`, `minishlink/web-push` (push notifications)
- **AI**: OpenAI (chat/coaching) and Anthropic (CSV-mapper) — both env-driven, both gracefully no-op without keys
- **Tests**: PHPUnit 11 via `./vendor/bin/phpunit` (NOT `php artisan test` — see Testing below)

## Directory map (by what's there, not by aspiration)
| Path | Purpose |
|------|---------|
| `app/Livewire/` | Full-page Livewire components (40+) registered directly in `routes/web.php` |
| `app/Livewire/Concerns/` | Shared Livewire traits |
| `app/Http/Controllers/` | REST + webhook controllers (`Webhooks/TwilioWebhookController` etc.) |
| `app/Models/` | Eloquent models (~130) |
| `app/Jobs/` | Queued jobs — only 6 today (`ProcessLeadImportChunk`, `QueueHealthProbeJob`, `Communications/`, `Finance/`) |
| `app/Services/` | Business logic services, organised by subsystem (`AI/`, `Chat/`, `Finance/`, `Payroll/`, `Twilio/`, etc.) |
| `app/Casts/` | `SafeEncrypted` cast for sensitive fields |
| `app/Enums/` | PHP-backed enums (sparse) |
| `app/Http/Requests/` | Form requests — currently NONE (validation lives inside Livewire components) |
| `app/Policies/` | **Only `ChargebackCasePolicy.php` is on disk today.** `ClientPolicy` is referenced in code but not present in this directory. Most entities have no policy. |
| `app/Repositories/` | One file (`StatisticsRepository`) which is never imported. Pattern was abandoned. |
| `resources/views/livewire/` | Blade views for Livewire components |
| `database/migrations/` | All schema changes (~104 files) |
| `database/seeders/` | `DatabaseSeeder` — early-returns outside `local`/`testing`/`development` |
| `routes/web.php` | Livewire page routes + 13 inline closures for video/meetings/push/presence |
| `routes/api.php` | JSON API endpoints (token-authenticated) |
| `routes/console.php` | Scheduler definitions (heartbeat, presence, dup scan, stuck-import recovery, weekly stats) |
| `azure/` | **Legacy** — not used by current Laravel Cloud deployment (per `PRODUCTION_DEPLOY.md`) |
| `tests/` | PHPUnit tests (currently 3 feature files, see Testing below for reality) |

## Coding standards
- PSR-12; no Pint config is committed, so format conservatively to match neighbours.
- Typed properties and return types in PHP classes.
- Livewire validation: prefer `#[Validate]` attributes or a `rules()` method. Avoid manual `validate()` calls; **but** today most components don't validate at all — when adding new code, validate.
- Authorization: prefer `$this->authorize(...)` + a Policy. Today coverage is sparse — when adding new code, add the Policy too rather than gating with `if ($user->hasRole(...))` only.
- Migrations: never edit existing migration files; add new ones with `php artisan make:migration`.
- Enums: backed enums in `app/Enums/`.
- Jobs must implement `ShouldQueue` and declare `$tries` and `$timeout`.
- Use `SafeEncrypted` cast for any field that would be sensitive in a database dump (cards, banking, login_info).

## Running the app — Windows local (verified)

There is **no `composer dev` script.** AGENTS.md previously claimed one; the claim is wrong. Don't try to run it.

```powershell
# 1. Install + create sqlite db
composer install --no-scripts          # --no-scripts dodges the post-autoload-dump
                                       # one-liner that misparses under cmd.exe/powershell
New-Item database/database.sqlite      # if not present

# 2. .env flips for local dev (production-tuned .env.example breaks loopback)
#   APP_ENV=local
#   APP_DEBUG=true
#   APP_URL=http://127.0.0.1:8000
#   DB_CONNECTION=sqlite
#   SESSION_DRIVER=file               # NOT 'database' — there is no sessions table;
#                                     #  this repo replaced the bundled users+sessions+cache
#                                     #  migration with its own users table only.
#   CACHE_STORE=file
#   QUEUE_CONNECTION=sync
#   SESSION_SECURE_COOKIE=false

# 3. Two migrations have MySQL-only SQL guarded by DB::getDriverName()==='mysql':
#      database/migrations/2026_04_04_100003_add_pipeline_tracking_fields_to_leads_and_deals.php
#      database/migrations/2026_04_27_000001_add_source_file_name_to_leads.php
#    Both backfills are no-ops on a fresh DB. If new migrations get added, scan with:
#      grep -nE "REGEXP|SHOW INDEX|UPDATE.*JOIN|UNSIGNED\)" database/migrations

# 4. Seed dev users (early-returns in non-dev environments)
php artisan migrate
php artisan db:seed --force

# 5. Serve. Vite/npm is not required to render Livewire — Tailwind ships compiled CSS.
php artisan serve
```

**Demo credentials** (created by `DatabaseSeeder` in dev only): `primeadmin / prime2026`, plus 10 role-typed users. These ONLY seed when `APP_ENV` is `local`/`testing`/`development`.

## Testing

The historical `php artisan test` command does not work in this repo — `nunomaduro/collision` is not in `require-dev`. Use phpunit directly:

```bash
./vendor/bin/phpunit                    # run all
./vendor/bin/phpunit --filter MyTest    # filter by class
./vendor/bin/phpunit tests/Unit         # one suite
```

Test config lives in `phpunit.xml` at repo root. Tests use `RefreshDatabase` against an in-memory sqlite database (`:memory:`), so they don't touch `database/database.sqlite`.

Required dev deps for tests to actually run:
- `phpunit/phpunit ^11.0`
- `mockery/mockery` — `RefreshDatabase` invokes `php artisan migrate` via `PendingCommand` which mocks internally
- `nunomaduro/collision` — pretty failure output (optional but expected by `php artisan test`)
- `fakerphp/faker` — used by existing factories and `fake()` helper in tests

If `composer install` was run before these were added, run `composer require --dev mockery/mockery nunomaduro/collision fakerphp/faker` to backfill.

## Key commands

```bash
php artisan make:livewire ComponentName      # New Livewire component
php artisan make:model ModelName -m          # Model + migration
php artisan make:job JobName                 # Queued job
php artisan make:request RequestName         # Form request (we want more of these)
php artisan make:policy MyPolicy --model=My  # Policy (we want more of these)
php artisan migrate                          # Run pending migrations
php artisan migrate:fresh --seed             # Wipe + reseed (sqlite dev only)
php artisan tinker                           # REPL
php artisan queue:listen --tries=1 --timeout=0   # Local queue worker (dev)
```

## Important patterns

- **Permissions**: `users.role` is a string; `users.permissions` is a JSON array. `User::hasRole(...)` checks the role string; `User::hasPerm($perm)` checks the permissions array AND short-circuits on `master_override`. The middleware aliases `role:` (`App\Http\Middleware\RequireRole`) and `perm:` (`RequirePermission`) gate routes.
- **Encrypted fields**: cast with `App\Casts\SafeEncrypted`. Today only `Deal::card_number` and `Deal::card_number2` are cast; the audit calls out `bank`, `login`, `app_login`, `name_on_card`, `login_info` as plaintext gaps.
- **Card-data display**: never render `card_number` directly to a Blade view. Use `$deal->masked_card` / `$deal->masked_card2` accessors. CVV columns were destroyed in `2026_04_20_000002_drop_cvv_columns_from_deals.php` and must never reappear.
- **Livewire pages**: registered directly with `Route::get('/path', \App\Livewire\ClassName::class)` in `routes/web.php`.
- **API auth**: `auth.token` middleware → custom bearer tokens hashed with sha256 in `api_tokens`. NOT Sanctum. 12-hour expiry, no scopes, no rotation today.
- **Twilio webhooks**: under `webhooks/twilio/*`, CSRF-exempt, signature-validated by `App\Http\Middleware\ValidateTwilioWebhook`.
- **Queue health**: `QueueHealthProbeJob` is a heartbeat job — do not remove it. `SelfHealingOrchestrator` runs on the scheduler and may auto-retry/auto-flush failed jobs (the audit flags this as masking real bugs).

## Do Not

- Don't edit files in `vendor/`.
- Don't edit existing migration files; add new ones.
- Don't store secrets in code; use `.env` variables. **Don't add `env('FOO', 'real-default')` for any sensitive value** — the audit had to strip hardcoded Twilio credentials from `config/services.php` and `config/twilio.php`. Default-less `env()` only.
- Don't render `card_number` / `card_number2` / `cv2` / `cv2_2` directly in any Blade view.
- Don't push directly to `main` without running the test suite.
- Don't run `npm run build` during dev — Tailwind CSS is shipped pre-built.
- Don't trust the legacy `azure/` directory; the deployment target is Laravel Cloud per `PRODUCTION_DEPLOY.md`.

## Known Gaps (active TODOs from the 2026-05-08 audit)

These are **not** myths — verified in code today. See `MEMORY.md` → "Production readiness audit" for fix list with file:line evidence.

- No multi-tenancy (single-tenant only).
- No `Contact`/`Account`/`Company`/`Opportunity` entities — leads → deals only.
- No transactional email (`config/mail.php` absent, 0 Mailables).
- No outbound webhooks; no global search; no calendar view.
- Voice/Dialer is a UI shell — only Twilio Video is actually wired.
- Two parallel commission engines (`CommissionCalculator` vs `Services/Payroll/DealPayrollCalculator`), no tests, double-pay risk in Payroll v2 batch builder.
- Five overlapping chargeback models, five overlapping dashboards, three overlapping audit-log models.
- 13 inline route closures in `routes/web.php` (video/meetings/push/presence) should move to controllers.
- Test coverage is essentially zero today (see Testing section).
