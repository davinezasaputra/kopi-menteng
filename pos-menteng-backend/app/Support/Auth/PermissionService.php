<?php

namespace App\Support\Auth;

use App\Models\User;
use App\Support\Tenancy\TenantContext;

class PermissionService
{
    public function __construct(private readonly TenantContext $context) {}

    public function hasPermission(User $user, string $permission): bool
    {
        $membership = $this->context->membership();
        if (! $membership || (int) $membership->user_id !== (int) $user->getAuthIdentifier()) {
            return false;
        }

        if ($membership->status !== 'active') {
            return false;
        }

        return $membership->role()
            ->with('permissions')
            ->first()
            ?->permissions
            ->contains('name', $permission) ?? false;
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
