<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Capsule\Manager as DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pin_hash', 255)->nullable()->after('pin');
        });

        DB::table('users')
            ->whereNotNull('pin')
            ->orderBy('id')
            ->each(function ($user) {
                if ($user->pin) {
                    DB::table('users')->where('id', $user->id)->update([
                        'pin_hash' => Hash::make((string) $user->pin),
                        'pin' => null,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pin_hash');
        });
    }
};
