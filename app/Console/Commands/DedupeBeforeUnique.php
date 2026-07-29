<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DedupeBeforeUnique extends Command
{
    protected $signature = 'data:dedupe-before-unique
                            {table?* : Table name(s) to scan: persons|categories|services|admin|staff|customers. Default: all.}
                            {--dry-run : Report duplicate groups without deleting anything.}
                            {--apply : Delete duplicate rows (keeps lowest id). Requires CONFIRM_DESTRUCTIVE_DEDUPE=YES.}';

    protected $description = 'Identify and (optionally) deduplicate rows that would block a unique-index migration. Never touches appointments.';

    public function handle(): int
    {
        $tables = $this->argument('table') ?: [
            'persons',
            'categories',
            'services',
            'admin',
            'staff',
            'customers',
        ];

        $apply = $this->option('apply');
        $dryRun = $this->option('dry-run') || ! $apply;

        if ($apply) {
            if (env('CONFIRM_DESTRUCTIVE_DEDUPE') !== 'YES') {
                $this->error('--apply requires CONFIRM_DESTRUCTIVE_DEDUPE=YES in the environment.');

                return self::FAILURE;
            }
            $this->warn('APPLY mode: duplicates will be DELETEd from disk.');
        } else {
            $this->info('DRY-RUN: no rows will be modified.');
        }

        foreach ($tables as $table) {
            $this->line("\n=== {$table} ===");
            $this->dedupe($table, $dryRun);
        }

        return self::SUCCESS;
    }

    /**
     * Per-table dedup. The group keys differ per table:
     *
     *   - persons:   phone_number (NULL allowed)
     *   - categories: name
     *   - services:  category_id + name
     *   - admin:     person_id
     *   - staff:     person_id
     *   - customers: person_id
     */
    private function dedupe(string $table, bool $dryRun): void
    {
        [$columns, $whereNotNull] = match ($table) {
            'persons' => [['phone_number'], ['phone_number']],
            'categories' => [['name'], []],
            'services' => [['category_id', 'name'], []],
            'admin', 'staff', 'customers' => [['person_id'], []],
            default => $this->unknown($table),
        };

        $select = array_merge($columns, [
            DB::raw('MIN(id) AS keep_id'),
            DB::raw('COUNT(*) AS c'),
        ]);

        $query = DB::table($table)->select($select)->groupBy(...$columns)->havingRaw('COUNT(*) > 1');

        foreach ($whereNotNull as $w) {
            $query->whereNotNull($w);
        }

        $rows = $query->get();

        if ($rows->isEmpty()) {
            $this->info("No duplicates in {$table}.");

            return;
        }

        $totalDeleted = 0;
        foreach ($rows as $row) {
            $whereClause = [];
            foreach ($columns as $c) {
                $whereClause[] = "{$c} = ".DB::getPdo()->quote($row->{$c});
            }
            $whereSql = implode(' AND ', $whereClause);
            $keep = (int) $row->keep_id;
            $count = (int) $row->c;
            $deleted = $count - 1;

            $this->line(sprintf(
                '  group(%s) → keep id=%d, delete %d rows',
                implode(',', array_map(fn ($c) => "{$c}=".var_export($row->{$c}, true), $columns)),
                $keep,
                $deleted,
            ));

            if (! $dryRun) {
                $result = DB::table($table)
                    ->whereRaw($whereSql)
                    ->where('id', '!=', $keep)
                    ->delete();
                $totalDeleted += $result;
                $this->line("    → deleted {$result} row(s)");
            }
        }

        if (! $dryRun) {
            $this->info("Done: {$totalDeleted} row(s) deleted from {$table}.");
        } else {
            $this->info('Re-run with --apply (and CONFIRM_DESTRUCTIVE_DEDUPE=YES) to delete.');
        }
    }

    private function unknown(string $table): array
    {
        $this->error("Unknown table: {$table}. Use one of: persons, categories, services, admin, staff, customers.");

        return [['id'], []];
    }
}
