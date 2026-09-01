<?php

namespace App\Support\Tenancy;

use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;

class OrganizationScope
{
    public function __construct(private readonly TenantContext $context)
    {
    }

    public function warehouseQuery(): Builder
    {
        $context = $this->context;

        $query = Warehouse::query()
            ->where('status', 'active')
            ->where('branch_id', $context->branchId())
            ->whereHas('branch.company', fn ($q) => $q->where('tenant_id', $context->tenantId()));

        $location = $context->membership()?->location;
        if (! $location) return $query;

        if ($location->type === 'warehouse') {
            return $query->where('location_id', $location->id);
        }

        if ($location->type === 'store') {
            $warehouseId = $location->settings['warehouse_id'] ?? null;
            if ($warehouseId !== null) return $query->whereKey((int) $warehouseId);

            return $query->where(function ($q): void {
                $q->whereNull('location_id')->where('is_default', true)
                    ->orWhereHas('location', fn ($locationQuery) => $locationQuery->where('type', 'warehouse')->where('branch_id', $this->context->branchId()));
            });
        }

        return $query->whereRaw('1 = 0');
    }

    public function warehouse(?int $warehouseId = null): ?Warehouse
    {
        $query = $this->warehouseQuery()->orderByDesc('is_default')->orderBy('name');
        return $warehouseId === null ? $query->first() : $query->whereKey($warehouseId)->first();
    }

    public function requireWarehouse(int $warehouseId): Warehouse
    {
        $warehouse = $this->warehouse($warehouseId);
        if ($warehouse) return $warehouse;

        abort(403, 'Warehouse berada di luar organization/location scope aktif.');
    }

    public function requireOperationalLocation(): Location
    {
        $location = $this->context->membership()?->location;
        abort_unless($location, 422, 'Location scope belum dipilih.');
        abort_if($location->status !== 'active', 403, 'Location tidak aktif.');
        abort_if($location->type === 'office', 403, 'Office tidak dapat digunakan untuk operasi kasir atau stok.');
        return $location;
    }
}
