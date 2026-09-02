<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $add = function (string $table, string $name, array $columns, bool $unique = false): void {
            if (! Schema::hasTable($table)) return;
            $exists = collect(Schema::getIndexes($table))->contains(fn ($index) => ($index['name'] ?? null) === $name);
            if ($exists) return;
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $name, $unique) {
                $unique ? $blueprint->unique($columns, $name) : $blueprint->index($columns, $name);
            });
        };

        $add('memberships', 'memberships_user_context_idx', ['user_id','tenant_id','company_id','branch_id','status','is_primary']);
        $add('roles', 'roles_tenant_code_unique', ['tenant_id','code'], true);
        $add('permissions', 'permissions_module_resource_action_unique', ['module','resource','action'], true);
        $add('role_permissions', 'role_permissions_role_permission_unique', ['role_id','permission_id'], true);
        $add('document_sequences', 'document_sequences_context_unique', ['tenant_id','company_id','branch_id','document_type','period'], true);
        $add('audit_logs', 'audit_logs_tenant_created_idx', ['tenant_id','created_at']);
    }

    public function down(): void
    {
        foreach ([
            ['memberships','memberships_user_context_idx'], ['roles','roles_tenant_code_unique'],
            ['permissions','permissions_module_resource_action_unique'], ['role_permissions','role_permissions_role_permission_unique'],
            ['document_sequences','document_sequences_context_unique'], ['audit_logs','audit_logs_tenant_created_idx'],
        ] as [$table,$index]) {
            if (! Schema::hasTable($table)) continue;
            $exists = collect(Schema::getIndexes($table))->contains(fn ($item) => ($item['name'] ?? null) === $index);
            if ($exists) Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($index));
        }
    }
};
