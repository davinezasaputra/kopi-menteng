<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite cannot drop a column while an index still references it.
        if (Schema::hasTable('general_ledgers')) {
            $indexes = DB::select("SELECT indexname FROM pg_indexes WHERE tablename = 'general_ledgers'");
            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                foreach ($indexes as $index) {
                    $indexName = $index->indexname ?? null;
                    if ($indexName && str_contains($indexName, 'general_ledgers_reference_type_reference_id')) {
                        DB::statement('DROP INDEX IF EXISTS "'.$indexName.'"');
                    }
                }
            } else {
                $sqliteIndexes = DB::select("SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = 'general_ledgers'");
                foreach ($sqliteIndexes as $index) {
                    $indexName = $index->name ?? null;
                    if ($indexName && str_contains($indexName, 'general_ledgers_reference_type_reference_id')) {
                        DB::statement('DROP INDEX IF EXISTS "'.$indexName.'"');
                    }
                }
            }

            $drop = array_values(array_filter([
                Schema::hasColumn('general_ledgers', 'reference_type') ? 'reference_type' : null,
                Schema::hasColumn('general_ledgers', 'reference_id') ? 'reference_id' : null,
            ]));

            if ($drop) {
                Schema::table('general_ledgers', function (Blueprint $table) use ($drop) {
                    $table->dropColumn($drop);
                });
            }

            if (! Schema::hasColumn('general_ledgers', 'reference_type') && ! Schema::hasColumn('general_ledgers', 'reference_id')) {
                Schema::table('general_ledgers', function (Blueprint $table) {
                    $table->string('reference_type')->nullable();
                    $table->uuid('reference_id')->nullable();
                    $table->index(['reference_type', 'reference_id']);
                });
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('general_ledgers')) {
            return;
        }

        $drop = array_values(array_filter([
            Schema::hasColumn('general_ledgers', 'reference_type') ? 'reference_type' : null,
            Schema::hasColumn('general_ledgers', 'reference_id') ? 'reference_id' : null,
        ]));

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