<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MigrateSqliteToPostgres extends Command
{
    protected $signature = 'data:migrate-sqlite-to-pgsql
                            {--truncate : Truncate destination tables before import}
                            {--include-infra : Include infra tables (cache/jobs/sessions/etc.)}
                            {--chunk=500 : Chunk size for reads/inserts}';

    protected $description = 'Copy data from SQLite (database/database.sqlite) into PostgreSQL preserving integer IDs';

    /**
     * Tables with integer auto-increment IDs we may need to sequence-fix.
     *
     * @var array<int, string>
     */
    private array $idTables = [
        'users',
        'clients',
        'payment_methods',
        'invoices',
        'invoice_line_items',
        'recurring_invoices',
        'jobs',
        'failed_jobs',
        'personal_access_tokens',
    ];

    /**
     * Business tables in dependency order (parents first).
     *
     * @var array<int, string>
     */
    private array $businessTables = [
        'users',
        'clients',
        'payment_methods',
        'invoices',
        'invoice_line_items',
        'recurring_invoices',
        'personal_access_tokens',
    ];

    /**
     * Infra tables to optionally migrate (non-business).
     *
     * @var array<int, string>
     */
    private array $infraTables = [
        'password_reset_tokens',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
    ];

    public function handle(): int
    {
        $src = DB::connection('sqlite_migrate');
        $dst = DB::connection('pgsql');

        $includeInfra = (bool) $this->option('include-infra');
        $chunk = max(1, (int) $this->option('chunk'));

        $tables = $this->businessTables;
        if ($includeInfra) {
            $tables = array_values(array_unique(array_merge($tables, $this->infraTables)));
        }

        $this->info('Source: sqlite_migrate (database/database.sqlite)');
        $this->info('Destination: pgsql');
        $this->info('Tables: '.implode(', ', $tables));

        if ((bool) $this->option('truncate')) {
            $this->warn('Truncating destination tables...');
            $this->truncateDestination($dst, $tables);
        }

        foreach ($tables as $table) {
            $this->migrateTable($src, $dst, $table, $chunk);
        }

        $this->info('Fixing Postgres sequences for id tables...');
        $this->fixSequences($dst);

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function truncateDestination($dst, array $tables): void
    {
        // Use CASCADE to handle FK dependencies; order doesn't matter.
        $quoted = array_map(fn (string $t) => '"'.$t.'"', $tables);
        $sql = 'TRUNCATE TABLE '.implode(', ', $quoted).' RESTART IDENTITY CASCADE;';
        $dst->statement($sql);
    }

    private function migrateTable($src, $dst, string $table, int $chunk): void
    {
        $count = (int) $src->table($table)->count();
        $this->line("Migrating {$table} ({$count} rows)...");

        if ($count === 0) {
            return;
        }

        // Prefer deterministic pagination.
        $orderColumn = $this->orderColumnFor($table);

        $src->table($table)
            ->orderBy($orderColumn)
            ->chunk($chunk, function ($rows) use ($dst, $table) {
                $payload = [];

                foreach ($rows as $row) {
                    $arr = (array) $row;
                    $payload[] = $this->transformRow($table, $arr);
                }

                $dst->table($table)->insert($payload);
            });
    }

    private function orderColumnFor(string $table): string
    {
        return match ($table) {
            'cache', 'cache_locks' => 'key',
            'job_batches' => 'id',
            'password_reset_tokens' => 'email',
            default => 'id',
        };
    }

    /**
     * Normalize row values so Postgres accepts them.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function transformRow(string $table, array $row): array
    {
        // Normalize JSON-ish columns that are casted as arrays in the app.
        if ($table === 'users') {
            foreach (['personal_address', 'postal_address', 'invoice_address'] as $col) {
                if (! array_key_exists($col, $row)) {
                    continue;
                }

                $val = $row[$col];
                if ($val === '' || $val === null) {
                    $row[$col] = null;
                    continue;
                }

                if (is_string($val)) {
                    $decoded = json_decode($val, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        // Keep JSON string; PG json column accepts JSON text.
                        $row[$col] = json_encode($decoded, JSON_UNESCAPED_UNICODE);
                    } else {
                        // Best-effort: store null rather than crashing the migration.
                        $row[$col] = null;
                    }
                }
            }
        }

        // Ensure failed_jobs.uuid exists if old rows were missing it.
        if ($table === 'failed_jobs') {
            if (! isset($row['uuid']) || trim((string) $row['uuid']) === '') {
                $row['uuid'] = (string) Str::uuid();
            }
        }

        return $row;
    }

    private function fixSequences($dst): void
    {
        foreach ($this->idTables as $table) {
            $max = (int) ($dst->table($table)->max('id') ?? 0);

            // pg_get_serial_sequence works for bigserial/serial-backed columns.
            $seq = $dst->selectOne("select pg_get_serial_sequence(?, ?) as seq", [$table, 'id']);
            $seqName = $seq?->seq ?? null;

            if (! is_string($seqName) || $seqName === '') {
                continue;
            }

            if ($max <= 0) {
                $dst->statement('select setval(?, 1, false)', [$seqName]);
            } else {
                $dst->statement('select setval(?, ?, true)', [$seqName, $max]);
            }
        }
    }
}

