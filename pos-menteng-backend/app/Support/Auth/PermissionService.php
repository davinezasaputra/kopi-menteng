<?php

namespace App\Support\Auth;

use App\Models\User;
use App\Support\Tenancy\TenantContext;

class PermissionService
{
    public function __construct(private readonly TenantContext $context) {}

    public function hasPermission(User $user, string $permission): bool
    {
        // Developer is the platform/application superuser (God Mode).
        // Tenant membership is still required by the tenant middleware when
        // accessing tenant-scoped endpoints, but no tenant RBAC permission
        // should block the developer account.
        if ($user->role === 'developer') {
            return true;
        }

        $membership = $this->context->membership();
        if (! $membership || (int) $membership->user_id !== (int) $user->getAuthIdentifier()) {
            return false;
        }

        if ($membership->status !== 'active') {
            return false;
        }

        $role = $membership->role()->with('permissions')->first();
        if (! $role) {
            return false;
        }

        // tenant-admin is the tenant-scoped super-admin role and is
        // intentionally allowed to access all tenant permissions.
        if ($role->code === 'tenant-admin') {
            return true;
        }

        return $role->permissions->contains('name', $permission);
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
