<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * This migration has been superseded.
     *
     * The original implementation (`up()`) silently deleted duplicate
     * rows including NULL phone numbers and historical appointments, then
     * added unique indexes. That behavior is destructive and not
     * appropriate for production data.
     *
     * It is replaced by:
     *   - 2026_08_15_000001_rename_catagory_to_category.php
     *   - 2026_08_15_000002_unique_constraints_v2.php
     *   - 2026_08_15_000003_soft_delete_parents.php
     *   - php artisan data:dedupe-before-unique --dry-run / --apply
     *
     * For existing installs that already applied this migration, the new
     * v2 migration is idempotent and only adds missing indexes; the data
     * that was deleted cannot be recovered, but no further destruction
     * is performed.
     */
    public function up(): void
    {
        // No-op: destructive dedupe + index creation moved to v2 pair.
    }

    public function down(): void
    {
        // No-op.
    }
};
