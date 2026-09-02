<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Support\Auth\PermissionService;
use App\Support\Tenancy\TenantContext;

trait ChecksFoundationPolicy
{
    protected function allows(User $user, string $permission, ?int $tenantId = null, ?int $companyId = null, ?int $branchId = null): bool
    {
        if (! app(PermissionService::class)->hasPermission($user, $permission)) return false;
        $context = app(TenantContext::class);
        if ($tenantId !== null && (int) $tenantId !== (int) $context->tenantId()) return false;
        if ($companyId !== null && (int) $companyId !== (int) $context->companyId()) return false;
        if ($branchId !== null && (int) $branchId !== (int) $context->branchId()) return false;
        return true;
    }
}
