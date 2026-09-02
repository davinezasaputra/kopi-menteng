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
        if (! Schema::hasColumn('users', 'pin_lookup')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('pin_lookup', 64)->nullable()->unique()->after('pin_hash');
            });
        }

        DB::table('users')->whereNotNull('pin')->orderBy('id')->each(function ($user) {
            $pin = (string) $user->pin;
            DB::table('users')->where('id', $user->id)->update([
                'pin_hash' => Hash::make($pin),
                'pin_lookup' => hash_hmac('sha256', $pin, (string) config('app.key')),
                'pin' => null,
            ]);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'pin_lookup')) {
            Schema::table('users', function (Blueprint $table) { $table->dropUnique(['pin_lookup']); $table->dropColumn('pin_lookup'); });
        }
        if (Schema::hasColumn('users', 'pin_hash')) {
            Schema::table('users', function (Blueprint $table) { $table->dropColumn('pin_hash'); });
        }
    }
};
