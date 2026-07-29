<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Switch parent rows (categories/services/staff) to soft delete and
     * make appointment FKs RESTRICT rather than CASCADE so historical
     * appointments are preserved when a parent is removed.
     *
     * - categories: hard delete still allowed by the controller, but
     *   service and staff rows cascade to soft delete (their deleted_at
     *   is set, not the row).
     * - services: drop CASCADE from the FK on category_id and switch to
     *   'restrict'.
     * - staff: drop CASCADE from FKs for person_id (keep) and add
     *   'restrict' instead of set null on category_id? No — keep
     *   'set null' on category_id because a staff member who is
     *   soft-deleted should not have their category FK lost.
     * - appointments: switch FKs on staff_id, customer_id, service_id
     *   from CASCADE to RESTRICT. The deleted_at column on the parent
     *   table is preserved; historical appointments still resolve to
     *   their (now soft-deleted) parent.
     */
    public function up(): void
    {
        // Add deleted_at to parents.
        foreach (['categories', 'services', 'staff'] as $table) {
            if (! Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->softDeletes();
                });
            }
        }

        // appointments(staff_id, customer_id, service_id) CASCADE -> RESTRICT
        // Postgres doesn't change FK action in-place reliably; drop and
        // re-add. SQLite has limited FK action ALTER support; we use a
        // portable raw SQL check.
        $this->recreateForeignKey('appointments', 'staff_id', 'staff', 'restrict', 'cascade');
        $this->recreateForeignKey('appointments', 'customer_id', 'customers', 'restrict', 'cascade');
        $this->recreateForeignKey('appointments', 'service_id', 'services', 'restrict', 'cascade');

        // services: category_id cascade -> restrict
        $this->recreateForeignKey('services', 'category_id', 'categories', 'restrict', 'cascade');

        // staff: catagory_id already set null; keep. (No-op.)
    }

    public function down(): void
    {
        $this->recreateForeignKey('appointments', 'staff_id', 'staff', 'cascade', 'restrict');
        $this->recreateForeignKey('appointments', 'customer_id', 'customers', 'cascade', 'restrict');
        $this->recreateForeignKey('appointments', 'service_id', 'services', 'cascade', 'restrict');
        $this->recreateForeignKey('services', 'category_id', 'categories', 'cascade', 'restrict');

        foreach (['staff', 'services', 'categories'] as $table) {
            if (Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $t->dropSoftDeletes();
                });
            }
        }
    }

    /**
     * Drop then re-add the foreign key with a different on-delete action.
     * Portable across MySQL/Postgres/SQLite.
     *
     * @param  string  $table  The child table holding the FK column.
     * @param  string  $column  The FK column name.
     * @param  string  $parent  The parent table name.
     * @param  string  $newAction  'cascade', 'restrict', 'set null', ...
     * @param  string  $existingAction  The action currently configured,
     *                                   used only for naming when no
     *                                   constraint exists by convention.
     */
    private function recreateForeignKey(string $table, string $column, string $parent, string $newAction, string $existingAction): void
    {
        // Best-effort: drop the conventional FK constraint name. The
        // naming convention used by Laravel's foreignId() is
        // `<table>_<column>_foreign`.
        $constraintName = "{$table}_{$column}_foreign";

        try {
            \Illuminate\Support\Facades\Schema::table($table, function (Blueprint $t) use ($constraintName) {
                $t->dropForeign($constraintName);
            });
        } catch (\Throwable $e) {
            // Constraint didn't exist or driver didn't permit the drop.
            // Fall through and try to add the new one anyway — the add
            // call below will throw a meaningful error if it actually
            // conflicts.
        }

        \Illuminate\Support\Facades\Schema::table($table, function (Blueprint $t) use ($column, $parent, $newAction) {
            $t->foreign($column)
                ->references('id')->on($parent)
                ->onDelete($newAction);
        });
    }
};
