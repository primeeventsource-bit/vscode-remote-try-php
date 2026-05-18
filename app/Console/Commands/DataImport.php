<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cross-engine data import — reads a data:export bundle and replays it into
 * the current connection. Disables FK checks for the import window, truncates
 * each target table (with --truncate, the default for safety in a dev sync),
 * batch-inserts preserving primary keys, then resets auto-increment sequences.
 *
 * Run AFTER `php artisan migrate --force` so the schema exists on the target.
 */
class DataImport extends Command
{
    protected $signature = 'data:import
        {--in= : Input directory containing manifest.json + *.jsonl (required)}
        {--batch=500 : Rows per INSERT batch}
        {--no-truncate : INSERT only — skip the pre-import TRUNCATE (default: truncate before insert)}
        {--skip= : Comma-separated tables to skip}
        {--only= : Comma-separated tables to include exclusively}
        {--dry-run : Parse the manifest and print plan without writing}';

    protected $description = 'Import a data:export bundle into the current DB connection (cross-engine safe).';

    public function handle(): int
    {
        $in = $this->option('in');
        if (!$in) {
            $this->error('--in=<dir> is required');
            return self::FAILURE;
        }
        $in = rtrim($in, '/\\');
        $manifestPath = $in . DIRECTORY_SEPARATOR . 'manifest.json';
        if (!is_file($manifestPath)) {
            $this->error("manifest.json not found at: {$manifestPath}");
            return self::FAILURE;
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        if (!is_array($manifest) || empty($manifest['tables'])) {
            $this->error('Manifest is empty or malformed.');
            return self::FAILURE;
        }

        $skip = array_filter(array_map('trim', explode(',', $this->option('skip') ?? '')));
        $only = array_filter(array_map('trim', explode(',', $this->option('only') ?? '')));
        $truncate = !$this->option('no-truncate');
        $batch = (int) $this->option('batch');
        $dryRun = (bool) $this->option('dry-run');

        $tables = array_keys($manifest['tables']);
        if ($only) $tables = array_values(array_intersect($tables, $only));
        if ($skip) $tables = array_values(array_diff($tables, $skip));

        $this->info("Source: driver={$manifest['source_driver']} db={$manifest['source_database']} at {$manifest['exported_at']}");
        $this->info("Target: driver=" . DB::getDriverName() . " db=" . DB::getDatabaseName());
        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Importing " . count($tables) . " tables, batch={$batch}, truncate=" . ($truncate ? 'yes' : 'no'));
        $this->line('');

        if ($dryRun) {
            foreach ($tables as $t) {
                $exists = Schema::hasTable($t);
                $this->line(sprintf('  %-40s %s rows  [%s]', $t, number_format($manifest['tables'][$t]), $exists ? 'table exists' : 'TABLE MISSING'));
            }
            return self::SUCCESS;
        }

        $this->disableForeignKeys();
        $totalImported = 0;
        $skipped = [];

        try {
            foreach ($tables as $table) {
                if (!Schema::hasTable($table)) {
                    $this->warn("  {$table}: target table does not exist, skipping");
                    $skipped[] = $table;
                    continue;
                }

                $path = $in . DIRECTORY_SEPARATOR . $table . '.jsonl';
                if (!is_file($path)) {
                    // manifest claimed 0 rows + we drop empty files in export — totally fine
                    if (($manifest['tables'][$table] ?? 0) === 0) continue;
                    $this->warn("  {$table}: jsonl missing, expected " . $manifest['tables'][$table] . " rows");
                    $skipped[] = $table;
                    continue;
                }

                if ($truncate) {
                    DB::table($table)->truncate();
                }

                $imported = $this->importTable($table, $path, $batch);
                $totalImported += $imported;

                if ($this->tableHasIdColumn($table)) {
                    $this->resetAutoIncrement($table);
                }

                $this->line(sprintf('  %-40s %s rows', $table, number_format($imported)));
            }
        } finally {
            $this->enableForeignKeys();
        }

        $this->line('');
        $this->info("Import complete: {$totalImported} rows imported across " . (count($tables) - count($skipped)) . " tables");
        if ($skipped) $this->warn("Skipped: " . implode(', ', $skipped));

        return self::SUCCESS;
    }

    private function importTable(string $table, string $jsonlPath, int $batch): int
    {
        $handle = fopen($jsonlPath, 'r');
        if (!$handle) return 0;

        $buffer = [];
        $count = 0;
        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') continue;
                $row = json_decode($line, true);
                if (!is_array($row)) continue;

                $buffer[] = $row;
                if (count($buffer) >= $batch) {
                    DB::table($table)->insert($buffer);
                    $count += count($buffer);
                    $buffer = [];
                }
            }
            if ($buffer) {
                DB::table($table)->insert($buffer);
                $count += count($buffer);
            }
        } finally {
            fclose($handle);
        }
        return $count;
    }

    private function disableForeignKeys(): void
    {
        match (DB::getDriverName()) {
            'mysql' => DB::statement('SET FOREIGN_KEY_CHECKS=0'),
            'sqlite' => DB::statement('PRAGMA foreign_keys = OFF'),
            'pgsql' => DB::statement("SET session_replication_role = 'replica'"),
            'sqlsrv' => null, // SQL Server: no global toggle; truncate may fail under FKs
            default => null,
        };
    }

    private function enableForeignKeys(): void
    {
        match (DB::getDriverName()) {
            'mysql' => DB::statement('SET FOREIGN_KEY_CHECKS=1'),
            'sqlite' => DB::statement('PRAGMA foreign_keys = ON'),
            'pgsql' => DB::statement("SET session_replication_role = 'origin'"),
            'sqlsrv' => null,
            default => null,
        };
    }

    private function resetAutoIncrement(string $table): void
    {
        try {
            $maxId = (int) DB::table($table)->max('id');
            if ($maxId <= 0) return;
            $next = $maxId + 1;
            match (DB::getDriverName()) {
                'mysql' => DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = {$next}"),
                'sqlite' => DB::table('sqlite_sequence')->updateOrInsert(['name' => $table], ['seq' => $maxId]),
                'pgsql' => DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), {$maxId})"),
                'sqlsrv' => DB::statement("DBCC CHECKIDENT ('{$table}', RESEED, {$maxId})"),
                default => null,
            };
        } catch (\Throwable $e) {
            $this->warn("    {$table}: could not reset sequence ({$e->getMessage()})");
        }
    }

    private function tableHasIdColumn(string $table): bool
    {
        try {
            return in_array('id', Schema::getColumnListing($table), true);
        } catch (\Throwable) {
            return false;
        }
    }
}
