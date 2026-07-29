<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rename the schema typo `catagory_id` → `category_id`.
     *
     * Touches:
     *   - staff.catagory_id            → staff.category_id
     *   - services.catagory_id         → services.category_id
     *   - the composite unique index services_catagory_id_name_unique
     *     is renamed to services_category_id_name_unique
     *
     * The previous v1 migration's index name is also updated to keep it
     * in sync with the column name.
     *
     * This migration is the contract for the Phase 1 schema rename. It
     * is irreversible-on-data, so:
     *
     *   1. The companion artisan command `data:export-baseline` writes
     *      a JSON dump of every affected row before this migration is
     *      queued in production.
     *   2. After applying this migration, run `php artisan migrate` on
     *      Render.
     */
    public function up(): void
    {
        // --- staff.catagory_id → staff.category_id ---------------------
        if (Schema::hasColumn('staff', 'catagory_id')
            && ! Schema::hasColumn('staff', 'category_id')) {
            // Drop the FK first (Postgres requires the column rename on
            // a column with a constraint to happen carefully). We don't
            // care about the constraint *name* — we drop and re-add.
            Schema::table('staff', function (Blueprint $table) {
                $table->dropForeign(['catagory_id']);
            });

            Schema::table('staff', function (Blueprint $table) {
                $table->renameColumn('catagory_id', 'category_id');
            });

            Schema::table('staff', function (Blueprint $table) {
                $table->foreign('category_id')
                    ->references('id')->on('categories')
                    ->onDelete('set null');
            });
        }

        // --- services.catagory_id → services.category_id ---------------
        if (Schema::hasColumn('services', 'catagory_id')
            && ! Schema::hasColumn('services', 'category_id')) {
            // Drop the v1 composite unique index whose name embeds the
            // old column name. Use a portable drop via raw SQL because
            // Schema::table doesn't have a `dropUniqueByColumns` helper.
            $this->dropUniqueIfExists('services', 'services_catagory_id_name_unique');

            Schema::table('services', function (Blueprint $table) {
                $table->dropForeign(['catagory_id']);
            });

            Schema::table('services', function (Blueprint $table) {
                $table->renameColumn('catagory_id', 'category_id');
            });

            Schema::table('services', function (Blueprint $table) {
                $table->foreign('category_id')
                    ->references('id')->on('categories')
                    ->onDelete('restrict');
            });

            // Recreate the unique index under the new name.
            Schema::table('services', function (Blueprint $table) {
                $table->unique(['category_id', 'name'], 'services_category_id_name_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('services', 'category_id')
            && ! Schema::hasColumn('services', 'catagory_id')) {
            $this->dropUniqueIfExists('services', 'services_category_id_name_unique');

            Schema::table('services', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
            });

            Schema::table('services', function (Blueprint $table) {
                $table->renameColumn('category_id', 'catagory_id');
            });

            Schema::table('services', function (Blueprint $table) {
                $table->foreign('catagory_id')
                    ->references('id')->on('categories')
                    ->onDelete('cascade');
            });

            Schema::table('services', function (Blueprint $table) {
                $table->unique(['catagory_id', 'name'], 'services_catagory_id_name_unique');
            });
        }

        if (Schema::hasColumn('staff', 'category_id')
            && ! Schema::hasColumn('staff', 'catagory_id')) {
            Schema::table('staff', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
            });

            Schema::table('staff', function (Blueprint $table) {
                $table->renameColumn('category_id', 'catagory_id');
            });

            Schema::table('staff', function (Blueprint $table) {
                $table->foreign('catagory_id')
                    ->references('id')->on('categories')
                    ->onDelete('set null');
            });
        }
    }

    /**
     * Portable unique-index drop.
     */
    private function dropUniqueIfExists(string $table, string $indexName): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE {$table} DROP INDEX {$indexName}");
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$indexName}");
            return;
        }

        if ($driver === 'sqlite') {
            // SQLite needs a recreate-table dance; skip silently if the
            // index doesn't exist.
            $exists = DB::selectOne(
                'SELECT COUNT(*) AS c FROM sqlite_master WHERE type = ? AND name = ?',
                ['index', $indexName],
            );
            if ((int) ($exists->c ?? 0) > 0) {
                $cols = DB::select(
                    'SELECT name FROM pragma_index_info(?) ORDER BY seqno',
                    [$indexName],
                );
                $colNames = array_map(fn ($r) => $r->name, $cols);
                if (! empty($colNames)) {
                    $colList = '"'.implode('","', $colNames).'"';
                    DB::statement("CREATE UNIQUE INDEX {$indexName}_tmp ON {$table} ({$colList})");
                    DB::statement("DROP INDEX {$indexName}");
                    DB::statement("DROP INDEX {$indexName}_tmp");
                }
            }
            return;
        }
    }
};
