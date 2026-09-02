<?php

namespace App\Support\Tenancy;

use App\Domain\Identity\Models\Membership;
use App\Domain\Organization\Models\Location;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use RuntimeException;

class TenantContext
{
    private ?Membership $membership = null;

    public function setMembership(Membership $membership): void { $this->membership = $membership->loadMissing(['tenant','company','branch','location','role']); }
    public function membership(): ?Membership { return $this->membership; }
    public function tenantId(): ?int { return $this->membership?->tenant_id; }
    public function companyId(): ?int { return $this->membership?->company_id; }
    public function branchId(): ?int { return $this->membership?->branch_id; }
    public function locationId(): ?int { return $this->membership?->location_id; }
    public function locationType(): ?string { return $this->membership?->location?->type; }

    public function resolveFor(Authenticatable $user, ?Request $request = null): Membership
    {
        $query = Membership::query()->where('user_id', $user->getAuthIdentifier())->where('status', 'active');
        $requestedTenant = $request?->header('X-Tenant-ID');
        $requestedCompany = $request?->header('X-Company-ID');
        $requestedBranch = $request?->header('X-Branch-ID');
        $requestedLocation = $request?->header('X-Location-ID');

        if ($requestedTenant !== null) $query->where('tenant_id', $requestedTenant);
        if ($requestedCompany !== null) $query->where('company_id', $requestedCompany);
        if ($requestedBranch !== null) $query->where('branch_id', $requestedBranch);
        if ($requestedLocation !== null) $query->where('location_id', $requestedLocation);

        $hasRequestedContext = $requestedTenant !== null || $requestedCompany !== null || $requestedBranch !== null || $requestedLocation !== null;
        $membership = $hasRequestedContext ? $query->first() : $query->where('is_primary', true)->first();

        if (! $membership) throw new RuntimeException('No active ERP membership is configured for this context.');
        if ($membership->location_id !== null) {
            $location = Location::query()->with('branch.company')->find($membership->location_id);
            if (! $location || (int) $location->branch_id !== (int) $membership->branch_id || (int) $location->branch->company_id !== (int) $membership->company_id || (int) $location->branch->company->tenant_id !== (int) $membership->tenant_id) {
                throw new RuntimeException('Membership location is outside the assigned organization scope.');
            }
        } elseif ($requestedLocation !== null) {
            throw new RuntimeException('The requested location is outside the user organization scope.');
        }

        $this->setMembership($membership);
        return $membership;
    }

    public function canAccessLocation(?int $locationId): bool
    {
        if ($locationId === null) return true;
        if ($this->membership?->user?->role === 'developer') return true;
        if ($this->locationId() !== null) return $this->locationId() === $locationId;
        if ($this->branchId() === null) return false;
        return Location::query()->whereKey($locationId)->where('branch_id', $this->branchId())->exists();
    }
}
