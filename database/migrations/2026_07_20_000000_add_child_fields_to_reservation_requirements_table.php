<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Group/Community baptisms cover several children under one
     * reservation, and each child needs their own checklist (birth
     * certificate, godparent eligibility, etc.) rather than one shared
     * checklist for the whole booking. `child_index` ties a requirement
     * row to a specific entry in `details.children` (0-based, matching
     * the array on the reservation), and `child_name` is snapshotted at
     * seed time so the label stays stable even if the child's name is
     * edited afterward. Both are null for every non-grouped reservation,
     * which keeps the single-checklist behavior unchanged everywhere else.
     *
     * Each step below is guarded so this migration can be safely re-run
     * after a prior partial failure (e.g. columns added but the unique
     * index swap failing) without erroring on "already exists".
     */
    public function up(): void
    {
        if (!Schema::hasColumn('reservation_requirements', 'child_index')) {
            Schema::table('reservation_requirements', function (Blueprint $table) {
                $table->unsignedInteger('child_index')->nullable()->after('reservation_id');
            });
        }

        if (!Schema::hasColumn('reservation_requirements', 'child_name')) {
            Schema::table('reservation_requirements', function (Blueprint $table) {
                $table->string('child_name')->nullable()->after('child_index');
            });
        }

        if (!$this->indexExists('reservation_requirements', 'reservation_requirements_reservation_id_child_index_key_unique')) {
            Schema::table('reservation_requirements', function (Blueprint $table) {
                $table->unique(['reservation_id', 'child_index', 'key']);
            });
        }

        if ($this->indexExists('reservation_requirements', 'reservation_requirements_reservation_id_key_unique')) {
            Schema::table('reservation_requirements', function (Blueprint $table) {
                $table->dropUnique(['reservation_id', 'key']);
            });
        }
    }

    public function down(): void
    {
        if (!$this->indexExists('reservation_requirements', 'reservation_requirements_reservation_id_key_unique')) {
            Schema::table('reservation_requirements', function (Blueprint $table) {
                $table->unique(['reservation_id', 'key']);
            });
        }

        if ($this->indexExists('reservation_requirements', 'reservation_requirements_reservation_id_child_index_key_unique')) {
            Schema::table('reservation_requirements', function (Blueprint $table) {
                $table->dropUnique(['reservation_id', 'child_index', 'key']);
            });
        }

        Schema::table('reservation_requirements', function (Blueprint $table) {
            if (Schema::hasColumn('reservation_requirements', 'child_index')) {
                $table->dropColumn('child_index');
            }
            if (Schema::hasColumn('reservation_requirements', 'child_name')) {
                $table->dropColumn('child_name');
            }
        });
    }

    /**
     * Whether an index with this name exists on the given table.
     *
     * IMPORTANT: this must stay driver-agnostic. It used to run a raw
     * `SHOW INDEX FROM ... WHERE Key_name = ?` query, which is MySQL-only
     * syntax — it silently worked in production (MySQL) but threw a
     * SQLSTATE[HY000] syntax error the moment this migration ran against
     * SQLite (e.g. the test suite's in-memory DB via RefreshDatabase),
     * since SQLite has no SHOW INDEX statement at all. Schema::getIndexes()
     * is Laravel's own driver-agnostic equivalent (supported on MySQL,
     * SQLite, PostgreSQL, and SQL Server alike) — use that instead of any
     * hand-rolled raw SQL here.
     */
    protected function indexExists(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))
            ->pluck('name')
            ->contains($indexName);
    }
};