<?php

namespace App\Support\Auth;

use App\Domain\Organization\Models\TenantLicense;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

class PermissionService
{
    public function __construct(private readonly TenantContext $context) {}

    public function hasPermission(User $user, string $permission): bool
    {
        if ($user->role === 'developer') return true;

        $membership = $this->context->membership();
        if (! $membership || (int) $membership->user_id !== (int) $user->getAuthIdentifier()) return false;
        if ($membership->status !== 'active') return false;

        $license = TenantLicense::query()->where('tenant_id', $membership->tenant_id)->first();
        if ($license && ! $license->allowsPermission($permission)) return false;

        $role = $membership->role()->with('permissions')->first();
        if (! $role) return false;

        if ($role->code === 'tenant-admin') {
            $overrides = $membership->permissionOverrides()->with('permission')->get();
            if ($overrides->isNotEmpty()) {
                return $overrides->contains(fn ($item) => $item->permission?->name === $permission);
            }
            return true;
        }

        // Legacy tenant-admin accounts may still use users.role=owner while
        // their ERP membership is being migrated to the tenant-admin role.
        if ($user->role === 'owner' && in_array($permission, [
            'organization.branch.view',
            'organization.branch.manage',
            'users.user.view',
            'users.user.create',
            'users.user.delete',
            'rbac.role.view',
            'audit.audit_log.view',
        ], true)) {
            return true;
        }

        $hasRolePermission = $role->permissions->contains('name', $permission);
        $hasOverridePermission = $membership->permissionOverrides()
            ->whereHas('permission', fn ($query) => $query->where('name', $permission))
            ->exists();

        return $hasRolePermission || $hasOverridePermission;
    }

    public function can(User $user, string $permission): bool
    {
        return $this->hasPermission($user, $permission);
    }

    public function authorize(User $user, string $permission): void
    {
        abort_unless($this->hasPermission($user, $permission), 403, 'Forbidden.');
    }
}
