<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only schema + data diagnostic.
 *
 *   php artisan audit:schema
 *   php artisan audit:schema --section=fk_missing_indexes
 *
 * Targets MySQL (Laravel Cloud). Every query is a SELECT — nothing is written.
 */
class AuditSchema extends Command
{
    protected $signature = 'audit:schema {--section= : run only one section}';

    protected $description = 'Read-only diagnostic of the production schema (MySQL)';

    public function handle(): int
    {
        $driver = DB::getDriverName();
        if ($driver !== 'mysql') {
            $this->error("audit:schema targets MySQL. Current driver: {$driver}");
            return self::FAILURE;
        }

        $db       = DB::getDatabaseName();
        $section  = $this->option('section');
        $sections = [
            'tables_no_indexes'    => fn() => $this->tablesWithoutIndexes($db),
            'fk_missing_indexes'   => fn() => $this->fkMissingIndexes($db),
            'deal_null_counts'     => fn() => $this->dealNullCounts(),
            'users_role_audit'     => fn() => $this->usersRoleAudit(),
            'payroll_duplicates'   => fn() => $this->payrollDuplicates(),
            'table_sizes'          => fn() => $this->tableSizes($db),
            'migrations_integrity' => fn() => $this->migrationsIntegrity(),
        ];

        if ($section && ! isset($sections[$section])) {
            $this->error("Unknown section: {$section}");
            $this->line('Valid: ' . implode(', ', array_keys($sections)));
            return self::FAILURE;
        }

        $this->line("Database: {$db} (MySQL)");

        foreach ($sections as $name => $fn) {
            if ($section && $section !== $name) {
                continue;
            }
            $this->line('');
            $this->line('═══ ' . strtoupper(str_replace('_', ' ', $name)) . ' ═══');
            try {
                $fn();
            } catch (\Throwable $e) {
                $this->error('Section failed: ' . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }

    private function tablesWithoutIndexes(string $db): void
    {
        $rows = DB::select('
            SELECT t.TABLE_NAME
            FROM INFORMATION_SCHEMA.TABLES t
            LEFT JOIN INFORMATION_SCHEMA.STATISTICS s
                ON t.TABLE_NAME = s.TABLE_NAME AND t.TABLE_SCHEMA = s.TABLE_SCHEMA
            WHERE t.TABLE_SCHEMA = ?
              AND t.TABLE_TYPE = "BASE TABLE"
              AND s.INDEX_NAME IS NULL
        ', [$db]);

        if (empty($rows)) {
            $this->info('All tables have at least one index.');
            return;
        }

        $this->warn(count($rows) . ' table(s) with no indexes:');
        foreach ($rows as $r) {
            $this->line('  • ' . $r->TABLE_NAME);
        }
    }

    private function fkMissingIndexes(string $db): void
    {
        $rows = DB::select('
            SELECT kcu.TABLE_NAME, kcu.COLUMN_NAME, kcu.REFERENCED_TABLE_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
            WHERE kcu.TABLE_SCHEMA = ?
              AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
              AND NOT EXISTS (
                SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS s
                WHERE s.TABLE_SCHEMA = kcu.TABLE_SCHEMA
                  AND s.TABLE_NAME   = kcu.TABLE_NAME
                  AND s.COLUMN_NAME  = kcu.COLUMN_NAME
                  AND s.SEQ_IN_INDEX = 1
              )
        ', [$db]);

        if (empty($rows)) {
            $this->info('All FK columns have a leading index.');
            return;
        }

        $this->warn(count($rows) . ' FK column(s) missing leading index (slow JOINs):');
        $this->table(
            ['Table', 'Column', 'References'],
            array_map(fn($r) => [$r->TABLE_NAME, $r->COLUMN_NAME, $r->REFERENCED_TABLE_NAME], $rows)
        );
    }

    private function dealNullCounts(): void
    {
        if (! Schema::hasTable('deals')) {
            $this->warn('deals table missing');
            return;
        }

        $total = DB::table('deals')->count();
        if ($total === 0) {
            $this->info('No deals yet.');
            return;
        }

        $columns  = Schema::getColumnListing('deals');
        $findings = [];

        foreach ($columns as $col) {
            $nulls = DB::table('deals')->whereNull($col)->count();
            $pct   = round(($nulls / $total) * 100, 1);
            if ($pct >= 95) {
                $findings[] = [$col, $nulls, $total, $pct . '%'];
            }
        }

        if (empty($findings)) {
            $this->info('No deal columns are ≥95% NULL.');
            return;
        }

        $this->warn('Columns ≥95% NULL — candidates for removal (abandoned features):');
        $this->table(['Column', 'NULL count', 'Total rows', 'NULL %'], $findings);
    }

    private function usersRoleAudit(): void
    {
        if (! Schema::hasTable('users')) {
            $this->warn('users table missing');
            return;
        }

        $total = DB::table('users')->count();
        $empty = DB::table('users')
            ->where(fn($q) => $q->whereNull('role')->orWhere('role', ''))
            ->count();

        $this->line("Total users: {$total}");
        $this->line("Empty-role users: {$empty}");

        if ($empty > 0) {
            $this->warn('Empty-role users (up to 10):');
            $rows = DB::table('users')
                ->where(fn($q) => $q->whereNull('role')->orWhere('role', ''))
                ->select('id', 'name', 'email')
                ->limit(10)
                ->get();
            foreach ($rows as $r) {
                $this->line("  • #{$r->id} {$r->name} <{$r->email}>");
            }
        }

        $dist = DB::table('users')
            ->selectRaw('COALESCE(NULLIF(role, ""), "(empty)") as role, COUNT(*) as c')
            ->groupBy('role')
            ->orderByDesc('c')
            ->get();

        $this->line('');
        $this->line('Role distribution:');
        $this->table(
            ['Role', 'Count'],
            $dist->map(fn($r) => [$r->role, $r->c])->toArray()
        );
    }

    private function payrollDuplicates(): void
    {
        $tables = DB::select('
            SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE "payroll%"
        ');

        $userCols = ['user_id', 'closer_user_id', 'fronter_user_id', 'admin_user_id'];
        $weekCols = ['week_start', 'week_start_date', 'week_key', 'pay_period_start', 'period_start'];

        $anyChecked = false;
        foreach ($tables as $t) {
            $name    = $t->TABLE_NAME;
            $columns = Schema::getColumnListing($name);
            $userCol = collect($userCols)->first(fn($c) => in_array($c, $columns));
            $weekCol = collect($weekCols)->first(fn($c) => in_array($c, $columns));

            if (! $userCol || ! $weekCol) {
                continue;
            }

            $anyChecked = true;
            $dupes = DB::table($name)
                ->select($userCol, $weekCol, DB::raw('COUNT(*) as c'))
                ->groupBy($userCol, $weekCol)
                ->having('c', '>', 1)
                ->limit(50)
                ->get();

            if ($dupes->isEmpty()) {
                $this->info("{$name} ({$userCol}, {$weekCol}): clean — no duplicates");
                continue;
            }

            $this->warn("{$name} ({$userCol}, {$weekCol}): " . $dupes->count() . ' duplicate group(s):');
            $this->table(
                [$userCol, $weekCol, 'count'],
                $dupes->map(fn($r) => [$r->{$userCol}, $r->{$weekCol}, $r->c])->toArray()
            );
        }

        if (! $anyChecked) {
            $this->line('No payroll_* table has both a user-id and week-start-like column — nothing to check.');
        }
    }

    private function tableSizes(string $db): void
    {
        $rows = DB::select('
            SELECT TABLE_NAME, TABLE_ROWS,
                   ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS size_mb
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = ?
            ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC
            LIMIT 20
        ', [$db]);

        $this->line('Top 20 tables by data+index size (TABLE_ROWS is approximate for InnoDB):');
        $this->table(
            ['Table', 'Rows (approx)', 'Size (MB)'],
            array_map(fn($r) => [$r->TABLE_NAME, $r->TABLE_ROWS ?? '—', $r->size_mb], $rows)
        );
    }

    private function migrationsIntegrity(): void
    {
        if (! Schema::hasTable('migrations')) {
            $this->warn('migrations table missing');
            return;
        }

        $dupes = DB::table('migrations')
            ->select('migration', DB::raw('COUNT(*) as c'))
            ->groupBy('migration')
            ->having('c', '>', 1)
            ->get();

        if ($dupes->isNotEmpty()) {
            $this->warn('Duplicate migration rows (data corruption):');
            $this->table(
                ['Migration', 'Count'],
                $dupes->map(fn($r) => [$r->migration, $r->c])->toArray()
            );
        } else {
            $this->info('No duplicate migration rows.');
        }

        $rowNames = DB::table('migrations')->pluck('migration');
        $files    = collect(glob(database_path('migrations/*.php')))
            ->map(fn($p) => pathinfo($p, PATHINFO_FILENAME));

        $orphans = $rowNames->diff($files);
        if ($orphans->isNotEmpty()) {
            $this->warn('Migration rows with no matching file:');
            foreach ($orphans as $o) {
                $this->line('  • ' . $o);
            }
        } else {
            $this->info('All migration rows have matching files.');
        }
    }
}
