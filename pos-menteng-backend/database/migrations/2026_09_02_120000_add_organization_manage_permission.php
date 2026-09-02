<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles') || ! Schema::hasTable('permission_role')) return;

        $permissionId = DB::table('permissions')->where('name', 'organization.branch.manage')->value('id');
        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'module' => 'organization',
                'resource' => 'branch',
                'action' => 'manage',
                'name' => 'organization.branch.manage',
            ]);
        }

        $roleIds = DB::table('roles')->where('code', 'tenant-admin')->pluck('id');
        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->updateOrInsert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ], []);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) return;
        $permissionId = DB::table('permissions')->where('name', 'organization.branch.manage')->value('id');
        if ($permissionId && Schema::hasTable('permission_role')) DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        if ($permissionId) DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
