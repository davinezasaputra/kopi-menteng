<?php

namespace Database\Seeders;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Organization\Models\Tenant;
use Illuminate\Database\Seeder;

class EnterprisePermissionSyncSeeder extends Seeder
{
    /**
     * Permissions required by every enterprise API route.
     * Existing tenant-admin permissions are preserved and these are added.
     */
    private const ROUTE_PERMISSIONS = [
        ['sales', 'fulfillment', 'view'],
        ['sales', 'fulfillment', 'create'],
        ['sales', 'fulfillment', 'pick'],
        ['sales', 'fulfillment', 'pack'],
        ['sales', 'shipment', 'view'],
        ['sales', 'shipment', 'create'],
        ['sales', 'invoice', 'view'],
        ['sales', 'invoice', 'create'],
        ['sales', 'receivable', 'view'],
        ['sales', 'payment', 'view'],
        ['sales', 'payment', 'create'],
        ['sales', 'return', 'view'],
        ['sales', 'return', 'create'],
        ['sales', 'reporting', 'view'],
        ['sales', 'approval_matrix', 'view'],
        ['sales', 'approval_matrix', 'create'],
        ['sales', 'order', 'view'],
        ['sales', 'order', 'create'],
        ['sales', 'order', 'submit'],
        ['sales', 'order', 'cancel'],
        ['sales', 'order', 'approve'],

        ['accounting', 'erp_account', 'view'],
        ['accounting', 'erp_account', 'create'],
        ['accounting', 'erp_journal', 'view'],
        ['accounting', 'erp_journal', 'create'],
        ['accounting', 'fiscal_period', 'view'],
        ['accounting', 'fiscal_period', 'manage'],
        ['accounting', 'report', 'view'],
        ['accounting', 'reconciliation', 'view'],
        ['accounting', 'reconciliation', 'create'],
        ['accounting', 'period', 'close'],

        ['inventory', 'stock', 'view'],
        ['inventory', 'stock', 'adjust'],

        ['purchasing', 'supplier', 'view'],
        ['purchasing', 'supplier', 'create'],
        ['purchasing', 'requisition', 'view'],
        ['purchasing', 'requisition', 'create'],
        ['purchasing', 'requisition', 'submit'],
        ['purchasing', 'requisition', 'cancel'],
        ['purchasing', 'order', 'view'],
        ['purchasing', 'order', 'create'],
        ['purchasing', 'order', 'submit'],
        ['purchasing', 'order', 'approve'],
        ['purchasing', 'order', 'cancel'],
        ['purchasing', 'receipt', 'view'],
        ['purchasing', 'receipt', 'create'],
        ['purchasing', 'ap', 'view'],
        ['purchasing', 'ap', 'create'],
        ['purchasing', 'ap', 'pay'],
        ['purchasing', 'reconciliation', 'view'],
        ['purchasing', 'reporting', 'view'],
        ['purchasing', 'return', 'view'],
        ['purchasing', 'return', 'create'],
        ['purchasing', 'credit_note', 'view'],
        ['purchasing', 'credit_note', 'create'],
        ['purchasing', 'budget', 'view'],
        ['purchasing', 'budget', 'create'],
        ['purchasing', 'approval_matrix', 'view'],
        ['purchasing', 'approval_matrix', 'create'],

        ['hr', 'employee', 'view'],
        ['hr', 'employee', 'manage'],
        ['pos', 'sale', 'view'],
        ['pos', 'sale', 'create'],
        ['pos', 'receipt_template', 'view'],
        ['pos', 'receipt_template', 'manage'],
        ['audit', 'audit_log', 'view'],
    ];

    public function run(): void
    {
        $permissionIds = [];

        foreach (self::ROUTE_PERMISSIONS as [$module, $resource, $action]) {
            $name = "{$module}.{$resource}.{$action}";

            $permission = Permission::updateOrCreate(
                ['name' => $name],
                [
                    'module' => $module,
                    'resource' => $resource,
                    'action' => $action,
                ]
            );

            $permissionIds[$name] = $permission->id;
        }

        foreach (Tenant::query()->pluck('id') as $tenantId) {
            $role = Role::query()
                ->where('tenant_id', $tenantId)
                ->where('code', 'tenant-admin')
                ->first();

            if (! $role) {
                continue;
            }

            $existing = $role->permissions()->pluck('permissions.id')->all();
            $role->permissions()->syncWithoutDetaching(array_merge($existing, array_values($permissionIds)));
        }
    }
}
