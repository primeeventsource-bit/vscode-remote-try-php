# AGENTS.md — Codex CLI Project Guide

## Project Overview
Laravel 13 + Livewire 4.2 CRM application (PHP 8.2). Full-stack: Blade/Livewire for UI, Tailwind CSS for styling, optional REST API layer. Runs on Azure App Service.

## Stack
- **Backend**: Laravel 13, PHP 8.2, Eloquent ORM
- **Frontend**: Livewire 4.2, Blade templates, Tailwind CSS, Vite
- **Queue**: Laravel Queue (database driver in dev, Redis in prod)
- **Auth**: Laravel built-in auth with role/permissions columns on `users` table
- **Comms**: Twilio (calls/SMS), push notifications via `minishlink/web-push`
- **Testing**: PHPUnit 12 via `php artisan test`

## Directory Conventions
| Path | Purpose |
|------|---------|
| `app/Livewire/` | Full-page and component Livewire classes |
| `app/Http/Controllers/` | REST/webhook controllers only |
| `app/Models/` | Eloquent models |
| `app/Jobs/` | Queued jobs |
| `app/Services/` | Business logic services |
| `app/Enums/` | PHP 8.1+ backed enums |
| `app/Http/Requests/` | Form request validation classes |
| `resources/views/livewire/` | Blade views for Livewire components |
| `database/migrations/` | All schema changes go here |
| `routes/web.php` | Livewire page routes (auth-protected) |
| `routes/api.php` | JSON API endpoints |
| `azure/` | Azure deployment scripts and nginx config |

## Coding Standards
- Follow PSR-12 code style (`./vendor/bin/pint` to fix)
- Use typed properties and return types in PHP classes
- Livewire components use `#[Validate]` attributes or `rules()` method for validation — not manual `validate()`
- Prefer `$this->authorize()` in Livewire components and controllers; policies live in `app/Policies/`
- New migrations: `php artisan make:migration` — never edit existing migration files
- Enums extend `string` or `int` backed PHP enums in `app/Enums/`
- Jobs must implement `ShouldQueue` and declare `$tries` and `$timeout`

## Running the App
```bash
# Full dev stack (server + queue + logs + vite)
composer dev

# Or individually:
php artisan serve
npm run dev
php artisan queue:listen --tries=1 --timeout=0
```

## Testing
```bash
php artisan test                  # Run all tests
php artisan test --filter MyTest  # Run specific test
./vendor/bin/pint                 # Fix code style
```

## Key Commands for Codex Tasks
```bash
php artisan make:livewire ComponentName   # New Livewire component
php artisan make:model ModelName -m       # Model + migration
php artisan make:job JobName              # Queued job
php artisan make:request RequestName      # Form request
php artisan migrate                       # Run pending migrations
php artisan tinker                        # REPL for debugging
```

## Important Patterns
- **Permissions**: Users have a JSON `permissions` column and a `role` column. Check `auth()->user()->permissions` array or use policies.
- **Encrypted data**: `app/Casts/SafeEncrypted.php` — use this cast for sensitive fields.
- **Livewire pages** are registered directly in `routes/web.php` using `Route::get('/path', \App\Livewire\ClassName::class)`.
- **API routes** use Laravel Sanctum token auth.
- **Queue health**: `QueueHealthProbeJob` is a heartbeat job — do not remove it.

## Do Not
- Do not edit files in `vendor/`
- Do not modify existing migration files
- Do not store secrets in code; use `.env` variables
- Do not run `npm run build` during dev — use `npm run dev`
- Do not push directly to `main` without running `php artisan test`
