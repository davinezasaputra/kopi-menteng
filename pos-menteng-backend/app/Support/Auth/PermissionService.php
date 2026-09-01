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

        $role = $membership->role()->with('permissions')->first();
        if (! $role) return false;
        if ($role->code === 'tenant-admin') {
            $license = TenantLicense::query()->where('tenant_id', $membership->tenant_id)->first();
            return $license ? $license->allowsPermission($permission) : true;
        }

        if ($license = TenantLicense::query()->where('tenant_id', $membership->tenant_id)->first()) {
            if (! $license->allowsPermission($permission)) return false;
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
