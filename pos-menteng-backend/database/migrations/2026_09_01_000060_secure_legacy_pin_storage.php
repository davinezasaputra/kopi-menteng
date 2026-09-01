<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'pin_hash')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('pin_hash', 255)->nullable()->after('pin');
            });
        }

        DB::table('users')
            ->whereNotNull('pin')
            ->orderBy('id')
            ->each(function ($user) {
                DB::table('users')->where('id', $user->id)->update([
                    'pin_hash' => Hash::make((string) $user->pin),
                    'pin' => null,
                ]);
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'pin_hash')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('pin_hash');
            });
        }
    }
};
