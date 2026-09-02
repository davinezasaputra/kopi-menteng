<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('general_ledgers')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $indexes = DB::select("SELECT indexname FROM pg_indexes WHERE tablename = 'general_ledgers'");
            foreach ($indexes as $index) {
                $indexName = $index->indexname ?? null;
                if ($indexName && str_contains($indexName, 'general_ledgers_reference_type_reference_id')) {
                    DB::statement('DROP INDEX IF EXISTS "'.$indexName.'"');
                }
            }
        } elseif ($driver === 'sqlite') {
            $indexes = DB::select("SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = 'general_ledgers'");
            foreach ($indexes as $index) {
                $indexName = $index->name ?? null;
                if ($indexName && str_contains($indexName, 'general_ledgers_reference_type_reference_id')) {
                    DB::statement('DROP INDEX IF EXISTS "'.$indexName.'"');
                }
            }
        }

        $drop = [];
        if (Schema::hasColumn('general_ledgers', 'reference_type')) {
            $drop[] = 'reference_type';
        }
        if (Schema::hasColumn('general_ledgers', 'reference_id')) {
            $drop[] = 'reference_id';
        }

        if ($drop) {
            Schema::table('general_ledgers', function (Blueprint $table) use ($drop) {
                $table->dropColumn($drop);
            });
        }

        if (! Schema::hasColumn('general_ledgers', 'reference_type')) {
            Schema::table('general_ledgers', function (Blueprint $table) {
                $table->string('reference_type')->nullable();
            });
        }
        if (! Schema::hasColumn('general_ledgers', 'reference_id')) {
            Schema::table('general_ledgers', function (Blueprint $table) {
                $table->uuid('reference_id')->nullable();
            });
        }

        Schema::table('general_ledgers', function (Blueprint $table) use ($driver) {
            $indexName = 'gl_reference_type_reference_id_idx';
            try {
                $table->index(['reference_type', 'reference_id'], $indexName);
            } catch (\Throwable) {
                // Index may already exist after a partially applied migration.
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('general_ledgers')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS "gl_reference_type_reference_id_idx"');
        } elseif ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS "gl_reference_type_reference_id_idx"');
        }

        $drop = [];
        if (Schema::hasColumn('general_ledgers', 'reference_type')) {
            $drop[] = 'reference_type';
        }
        if (Schema::hasColumn('general_ledgers', 'reference_id')) {
            $drop[] = 'reference_id';
        }

        if ($drop) {
            Schema::table('general_ledgers', function (Blueprint $table) use ($drop) {
                $table->dropColumn($drop);
            });
        }

        Schema::table('general_ledgers', function (Blueprint $table) {
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
        });
    }
};