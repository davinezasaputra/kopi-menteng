<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $map = [
            'accounting.erp_account.view' => ['erp_account','view'],
            'accounting.erp_account.create' => ['erp_account','create'],
            'accounting.erp_journal.view' => ['erp_journal','view'],
            'accounting.erp_journal.create' => ['erp_journal','create'],
        ];

        foreach ($map as $name => [$resource, $action]) {
            $row = DB::table('permissions')->where('name', $name)->first();

            if ($row) {
                DB::table('permissions')
                    ->where('id', $row->id)
                    ->update([
                        'module' => 'accounting',
                        'resource' => $resource,
                        'action' => $action,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('permissions')->insert([
                    'module' => 'accounting',
                    'resource' => $resource,
                    'action' => $action,
                    'name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $ids = DB::table('permissions')
            ->whereIn('name', array_keys($map))
            ->pluck('id');

        $roleIds = DB::table('roles')
            ->whereIn('code', ['tenant-admin','accountant'])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($ids as $permissionId) {
                DB::table('role_permissions')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')
            ->whereIn('name', [
                'accounting.erp_account.view',
                'accounting.erp_account.create',
                'accounting.erp_journal.view',
                'accounting.erp_journal.create',
            ])->pluck('id');

        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
