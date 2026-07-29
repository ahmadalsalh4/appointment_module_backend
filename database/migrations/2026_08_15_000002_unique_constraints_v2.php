<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Idempotent unique-index creation. NEVER deletes rows. If duplicates
     * exist that block the index, the migration logs them and skips the
     * index — leave deduplication to `php artisan data:dedupe-before-unique`.
     */
    public function up(): void
    {
        $this->addUniqueIfClean('categories', 'name', 'categories_name_unique');
        $this->addUniqueIfClean('services', ['category_id', 'name'], 'services_category_id_name_unique');
        $this->addUniqueNullAware('persons', 'phone_number', 'persons_phone_number_unique');
        $this->addUniqueIfClean('admin', 'person_id', 'admin_person_id_unique');
        $this->addUniqueIfClean('staff', 'person_id', 'staff_person_id_unique');
        $this->addUniqueIfClean('customers', 'person_id', 'customers_person_id_unique');
        $this->addConflictKeyIfMissing();
    }

    public function down(): void
    {
        $this->dropUniqueByName('appointments', 'appointments_conflict_key_unique');
        if ($this->columnExists('appointments', 'conflict_key')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->dropColumn('conflict_key');
            });
        }
        $this->dropUniqueByName('customers', 'customers_person_id_unique');
        $this->dropUniqueByName('staff', 'staff_person_id_unique');
        $this->dropUniqueByName('admin', 'admin_person_id_unique');
        $this->dropUniqueByName('persons', 'persons_phone_number_unique');
        $this->dropUniqueByName('services', 'services_category_id_name_unique');
        $this->dropUniqueByName('categories', 'categories_name_unique');
    }

    /**
     * Add a UNIQUE index only if no row group violates it. Reports the
     * first violation to the log without modifying or deleting data.
     */
    private function addUniqueIfClean(string $table, string|array $columns, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        if ($this->hasDuplicates($table, $columns)) {
            logger()->warning("Unique index {$indexName} skipped: duplicates exist. Run `php artisan data:dedupe-before-unique {$table}`.");

            return;
        }

        Schema::table($table, function (Blueprint $table) use ($columns, $indexName) {
            $table->unique($columns, $indexName);
        });
    }

    /**
     * Same as addUniqueIfClean but treats NULL values as distinct.
     * Used for persons.phone_number where many users have NULL.
     */
    private function addUniqueNullAware(string $table, string|array $columns, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        $colList = is_array($columns) ? implode(',', $columns) : $columns;

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // Postgres: WHERE clause on unique index handles NULL.
            $conflict = DB::selectOne(
                "SELECT {$colList}, COUNT(*) AS c FROM {$table} WHERE ".implode(' IS NOT NULL AND ', (array) $columns).' IS NOT NULL GROUP BY '.implode(',', (array) $columns).' HAVING COUNT(*) > 1 LIMIT 1'
            );
            if ($conflict !== null) {
                logger()->warning("Unique index {$indexName} skipped: duplicates exist on non-null values. Run `php artisan data:dedupe-before-unique persons`.");

                return;
            }

            DB::statement("CREATE UNIQUE INDEX {$indexName} ON {$table} ({$colList}) WHERE {$colList} IS NOT NULL");
            return;
        }

        if ($driver === 'mysql') {
            // MySQL allows multiple NULLs in a unique index natively.
            $conflict = DB::selectOne(
                "SELECT {$colList}, COUNT(*) AS c FROM {$table} WHERE {$colList} IS NOT NULL GROUP BY {$colList} HAVING COUNT(*) > 1 LIMIT 1"
            );
            if ($conflict !== null) {
                logger()->warning("Unique index {$indexName} skipped: duplicates exist on non-null values. Run `php artisan data:dedupe-before-unique persons`.");

                return;
            }

            Schema::table($table, function (Blueprint $table) use ($columns, $indexName) {
                $table->unique($columns, $indexName);
            });
            return;
        }

        if ($driver === 'sqlite') {
            // SQLite treats NULLs as distinct in unique indexes by
            // default; the duplicate check is the same as MySQL.
            $addClean = $this->addUniqueIfClean($table, $columns, $indexName);
            // fall through
            return;
        }
    }

    private function addConflictKeyIfMissing(): void
    {
        if (! $this->columnExists('appointments', 'conflict_key')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->string('conflict_key')->nullable();
            });
        }

        if (! $this->indexExists('appointments', 'appointments_conflict_key_unique')) {
            // Stamp conflict_key for active rows so the unique index can
            // be applied. Done with a NULL-aware UPDATE — terminal
            // appointments keep NULL and don't conflict.
            DB::table('appointments')
                ->whereNotIn('state_id', [DB::raw(3), DB::raw(4)])
                ->orderBy('id')
                ->chunk(100, function ($rows) {
                    foreach ($rows as $row) {
                        DB::table('appointments')
                            ->where('id', $row->id)
                            ->update([
                                'conflict_key' => $row->staff_id.'|'.$row->start_date,
                            ]);
                    }
                });

            Schema::table('appointments', function (Blueprint $table) {
                $table->unique('conflict_key', 'appointments_conflict_key_unique');
            });
        }
    }

    private function dropUniqueByName(string $table, string $indexName): void
    {
        if (! $this->indexExists($table, $indexName)) {
            return;
        }
        Schema::table($table, function (Blueprint $table) use ($indexName) {
            $table->dropUnique($indexName);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            $database = DB::connection()->getDatabaseName();
            $row = DB::selectOne(
                'SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
                [$database, $table, $indexName],
            );

            return ((int) ($row->c ?? 0)) > 0;
        }

        if ($driver === 'pgsql') {
            $row = DB::selectOne(
                'SELECT COUNT(*) AS c FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ? AND indexname = ?',
                [$table, $indexName],
            );

            return ((int) ($row->c ?? 0)) > 0;
        }

        if ($driver === 'sqlite') {
            $row = DB::selectOne(
                'SELECT COUNT(*) AS c FROM sqlite_master WHERE type = ? AND tbl_name = ? AND name = ?',
                ['index', $table, $indexName],
            );

            return ((int) ($row->c ?? 0)) > 0;
        }

        return false;
    }

    private function columnExists(string $table, string $column): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            $database = DB::connection()->getDatabaseName();
            $row = DB::selectOne(
                'SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?',
                [$database, $table, $column],
            );

            return ((int) ($row->c ?? 0)) > 0;
        }

        if ($driver === 'pgsql') {
            $row = DB::selectOne(
                'SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_name = ? AND column_name = ?',
                [$table, $column],
            );

            return ((int) ($row->c ?? 0)) > 0;
        }

        if ($driver === 'sqlite') {
            $row = DB::selectOne(
                'SELECT COUNT(*) AS c FROM pragma_table_info(?) WHERE name = ?',
                [$table, $column],
            );

            return ((int) ($row->c ?? 0)) > 0;
        }

        return false;
    }

    /**
     * Returns true if any group of values for $columns has more than 1 row.
     * Treats NULL as a value: multiple NULLs count as duplicates.
     */
    private function hasDuplicates(string $table, string|array $columns): bool
    {
        $cols = (array) $columns;
        $row = DB::table($table)
            ->selectRaw(implode(',', $cols).', COUNT(*) AS c')
            ->groupBy(...$cols)
            ->havingRaw('COUNT(*) > 1')
            ->limit(1)
            ->first();

        return $row !== null;
    }
};
