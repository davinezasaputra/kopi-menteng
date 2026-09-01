<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Kept as a no-op for migration-history compatibility.
     * The actual location_id column is created after locations by the next
     * migration, preventing fresh-install ordering failures.
     */
    public function up(): void
    {
    }

    public function down(): void
    {
    }
};
