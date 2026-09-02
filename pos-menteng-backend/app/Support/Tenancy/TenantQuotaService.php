<?php

namespace App\Support\Tenancy;

use App\Domain\Identity\Models\Membership;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\TenantLicense;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TenantQuotaService
{
    public function license(int $tenantId): ?TenantLicense
    {
        return TenantLicense::query()->where('tenant_id', $tenantId)->first();
    }

    public function assertCanCreate(int $tenantId, string $resource): void
    {
        $license = $this->license($tenantId);

        if (! $license || ! $license->isActive()) {
            throw new HttpException(422, 'Lisensi tenant tidak aktif atau belum dikonfigurasi.', null, ['X-Quota-Code' => 'LICENSE_INACTIVE']);
        }

        $limit = match ($resource) {
            'user' => $license->max_users,
            'company' => $license->max_companies,
            'branch' => $license->max_branches,
            'location' => $license->max_locations,
            default => throw new \InvalidArgumentException("Unsupported tenant quota resource [{$resource}]."),
        };

        if ($limit === null) return;

        $usage = match ($resource) {
            'user' => Membership::query()->where('tenant_id', $tenantId)->where('status', 'active')->count(),
            'company' => Company::query()->where('tenant_id', $tenantId)->where('status', 'active')->count(),
            'branch' => Branch::query()->whereHas('company', fn ($q) => $q->where('tenant_id', $tenantId))->where('status', 'active')->count(),
            'location' => Location::query()->whereHas('branch.company', fn ($q) => $q->where('tenant_id', $tenantId))->where('status', 'active')->count(),
        };

        if ($usage >= $limit) {
            $label = match ($resource) {
                'user' => 'user',
                'company' => 'company',
                'branch' => 'branch',
                'location' => 'location',
            };
            throw new HttpException(422, "Batas {$label} lisensi tercapai ({$limit}). Upgrade lisensi untuk menambah {$label}.", null, [
                'X-Quota-Code' => 'QUOTA_EXCEEDED',
                'X-Quota-Resource' => $resource,
                'X-Quota-Limit' => (string) $limit,
                'X-Quota-Usage' => (string) $usage,
            ]);
        }
    }

    public function lockTenant(int $tenantId): void
    {
        DB::table('tenants')->where('id', $tenantId)->lockForUpdate()->first();
    }
}
