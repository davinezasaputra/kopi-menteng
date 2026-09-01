<?php

namespace App\Support\Tenancy;

use App\Domain\Identity\Models\Membership;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use RuntimeException;

class TenantContext
{
    private ?Membership $membership = null;

    public function setMembership(Membership $membership): void { $this->membership = $membership; }
    public function membership(): ?Membership { return $this->membership; }
    public function tenantId(): ?int { return $this->membership?->tenant_id; }
    public function companyId(): ?int { return $this->membership?->company_id; }
    public function branchId(): ?int { return $this->membership?->branch_id; }

    public function resolveFor(Authenticatable $user, ?Request $request = null): Membership
    {
        $query = Membership::query()->where('user_id', $user->getAuthIdentifier())->where('status', 'active');
        $requestedTenant = $request?->header('X-Tenant-ID');
        $requestedCompany = $request?->header('X-Company-ID');
        $requestedBranch = $request?->header('X-Branch-ID');

        if ($requestedTenant !== null) $query->where('tenant_id', $requestedTenant);
        if ($requestedCompany !== null) $query->where('company_id', $requestedCompany);
        if ($requestedBranch !== null) $query->where('branch_id', $requestedBranch);

        $membership = ($requestedTenant !== null || $requestedCompany !== null || $requestedBranch !== null)
            ? $query->first()
            : $query->where('is_primary', true)->first();

        if (! $membership) throw new RuntimeException('No active ERP membership is configured for this context.');
        $this->setMembership($membership);
        return $membership;
    }
}
