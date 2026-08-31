<?php

namespace App\Support\Tenancy;

use App\Domain\Identity\Models\Membership;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

class TenantContext
{
    private ?Membership $membership = null;

    public function setMembership(Membership $membership): void { $this->membership = $membership; }
    public function membership(): ?Membership { return $this->membership; }
    public function tenantId(): ?int { return $this->membership?->tenant_id; }
    public function companyId(): ?int { return $this->membership?->company_id; }
    public function branchId(): ?int { return $this->membership?->branch_id; }

    public function resolveFor(Authenticatable $user): Membership
    {
        $membership = Membership::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('status', 'active')
            ->where('is_primary', true)
            ->first();

        if (! $membership) {
            throw new RuntimeException('No active ERP membership is configured for this user.');
        }

        $this->setMembership($membership);
        return $membership;
    }
}
