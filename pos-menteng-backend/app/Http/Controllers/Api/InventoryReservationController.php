<?php

namespace App\Http\Controllers\Api;

use App\Domain\Inventory\Models\InventoryReservation;
use App\Domain\Inventory\Services\InventoryReservationService;
use App\Domain\Organization\Models\Warehouse;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryReservationController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly InventoryReservationService $service,
    ) {}

    public function index(): JsonResponse
    {
        $rows = InventoryReservation::query()
            ->with(['warehouse', 'items.product', 'creator'])
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->companyId())
            ->where('branch_id', $this->context->branchId())
            ->latest('id')
            ->paginate(50);

        return response()->json(['status' => 'success', 'data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'reference_type' => ['nullable', 'string', 'max:100'],
            'reference_id' => ['nullable', 'string', 'max:100'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'notes' => ['nullable', 'string'],
        ]);

        $reservation = $this->service->reserve(
            Warehouse::findOrFail($data['warehouse_id']),
            $data['items'],
            $data['reference_type'] ?? null,
            $data['reference_id'] ?? null,
            $data['expires_at'] ?? null,
            $data['notes'] ?? null,
        );

        return response()->json(['status' => 'success', 'message' => 'Stock reserved.', 'data' => $reservation], 201);
    }

    public function show(int $opname): JsonResponse
    {
        $reservation = InventoryReservation::query()
            ->with(['warehouse', 'items.product', 'creator', 'releaser', 'fulfiller'])
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->companyId())
            ->where('branch_id', $this->context->branchId())
            ->findOrFail($opname);

        return response()->json(['status' => 'success', 'data' => $reservation]);
    }

    public function release(int $reservation): JsonResponse
    {
        $row = InventoryReservation::findOrFail($reservation);
        $result = $this->service->release($row);
        return response()->json(['status' => 'success', 'message' => 'Reservation released.', 'data' => $result]);
    }

    public function fulfill(int $reservation): JsonResponse
    {
        $row = InventoryReservation::findOrFail($reservation);
        $result = $this->service->fulfill($row);
        return response()->json(['status' => 'success', 'message' => 'Reservation fulfilled.', 'data' => $result]);
    }
}