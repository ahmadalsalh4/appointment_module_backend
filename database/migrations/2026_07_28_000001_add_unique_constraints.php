<?php

use App\Models\Status;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // categories.name unique
        $this->addUniqueIfMissing('categories', 'name', 'categories_name_unique');

        // services(catagory_id, name) unique composite
        $this->addUniqueIfMissing('services', ['catagory_id', 'name'], 'services_catagory_id_name_unique');

        // persons.phone_number unique (only when not null)
        $this->addUniqueIfMissing('persons', 'phone_number', 'persons_phone_number_unique');

        // person_id unique on each of admin, staff, customers
        $this->addUniqueIfMissing('admin', 'person_id', 'admin_person_id_unique');
        $this->addUniqueIfMissing('staff', 'person_id', 'staff_person_id_unique');
        $this->addUniqueIfMissing('customers', 'person_id', 'customers_person_id_unique');

        // The critical double-booking guard: a staff member can have only one
        // ACTIVE appointment at a given start_date. We use a deterministic
        // `conflict_key` column (set by the Appointment model on save) that
        // is NULL for COMPLETED/CANCELLED rows. The unique index treats
        // NULLs as non-conflicting on every supported driver, so a finished
        // appointment at 10:00 doesn't block a fresh booking at 10:00.
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
        $this->dropUniqueByName('services', 'services_catagory_id_name_unique');
        $this->dropUniqueByName('categories', 'categories_name_unique');
    }

    /**
     * Adds a unique index by name, after first removing any duplicate
     * rows that would otherwise prevent the constraint. Skips itself
     * if the index already exists.
     */
    private function addUniqueIfMissing(string $table, string|array $columns, string $indexName, ?string $groupKey = null): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        // De-duplicate rows that violate the constraint we're about
        // to add. Only relevant when the table has data (i.e. not on
        // a fresh install).
        $this->dedupeByKey($table, $columns, $groupKey ?? $columns);

        Schema::table($table, function (Blueprint $table) use ($columns, $indexName) {
            $table->unique($columns, $indexName);
        });
    }

    /**
     * Adds the appointments.conflict_key column + unique index. The
     * Appointment model keeps this column in sync on save (NULL for
     * terminal states, "<staff>|<start>" for active ones). NULL values
     * don't conflict under a unique index on any supported driver, so
     * finished bookings don't block fresh bookings at the same slot.
     */
    private function addConflictKeyIfMissing(): void
    {
        if (! $this->columnExists('appointments', 'conflict_key')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->string('conflict_key')->nullable();
            });
        }

        if (! $this->indexExists('appointments', 'appointments_conflict_key_unique')) {
            // De-duplicate any existing rows by (staff_id, start_date),
            // keeping the lowest id per group. Then stamp the conflict_key
            // for active rows so the unique index can be applied.
            $this->dedupeByKey('appointments', ['staff_id', 'start_date'], ['staff_id', 'start_date']);
            $this->seedConflictKeys();

            Schema::table('appointments', function (Blueprint $table) {
                $table->unique('conflict_key', 'appointments_conflict_key_unique');
            });
        }
    }

    /**
     * Walk the appointments table and stamp conflict_key on active rows.
     * Mirrors the logic in Appointment::booted().
     */
    private function seedConflictKeys(): void
    {
        $terminalStates = [Status::COMPLETED, Status::CANCELLED];
        DB::table('appointments')
            ->whereNotIn('state_id', $terminalStates)
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
    }

    /**
     * Drop a unique index by name if it exists. No-op on databases
     * that don't support DROP INDEX IF EXISTS.
     */
    private function dropUniqueByName(string $table, string $indexName): void
    {
        if (! $this->indexExists($table, $indexName)) {
            return;
        }
        Schema::table($table, function (Blueprint $table) use ($indexName) {
            $table->dropUnique($indexName);
        });
    }

    /**
     * Portable index-existence check. Uses information_schema on
     * MySQL/PostgreSQL and the SQLite master table on SQLite. Avoids
     * the Doctrine schema manager which isn't available on every
     * driver.
     */
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

    /**
     * Portable column-existence check, mirroring indexExists().
     */
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
     * Delete duplicate rows, keeping the lowest id for each group of
     * duplicate key values. Only fires when the table has data.
     */
    private function dedupeByKey(string $table, string|array $columns, string|array $groupKey): void
    {
        $keys = is_array($groupKey) ? $groupKey : [$groupKey];
        $select = array_merge($keys, [DB::raw('MIN(id) AS keep_id'), DB::raw('COUNT(*) AS c')]);
        $rows = DB::table($table)
            ->select($select)
            ->groupBy(...$keys)
            ->havingRaw('COUNT(*) > 1')
            ->get();
        foreach ($rows as $row) {
            $query = DB::table($table);
            foreach ($keys as $k) {
                $query->where($k, $row->{$k});
            }
            $query->where('id', '!=', $row->keep_id)->delete();
        }
    }
};
