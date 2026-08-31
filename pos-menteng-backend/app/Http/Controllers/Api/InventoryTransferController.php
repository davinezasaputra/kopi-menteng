<?php

namespace App\Http\Controllers\Api;

use App\Domain\Inventory\Models\InventoryTransfer;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Warehouse;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryTransferController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly InventoryService $inventory,
    ) {
    }

    public function index(): JsonResponse
    {
        $transfers = InventoryTransfer::query()
            ->with(['product', 'fromBranch', 'fromWarehouse', 'toBranch', 'toWarehouse', 'creator'])
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->companyId())
            ->latest('id')
            ->paginate(50);

        return response()->json(['status' => 'success', 'data' => $transfers]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_warehouse_id' => ['required','integer','exists:warehouses,id'],
            'to_warehouse_id' => ['required','integer','exists:warehouses,id','different:from_warehouse_id'],
            'product_id' => ['required','exists:products,id'],
            'quantity' => ['required','numeric','gt:0'],
            'unit_cost' => ['nullable','numeric','gte:0'],
            'notes' => ['nullable','string'],
        ]);

        $fromWarehouse = Warehouse::with('branch')->findOrFail($data['from_warehouse_id']);
        $toWarehouse = Warehouse::with('branch')->findOrFail($data['to_warehouse_id']);
        $product = Product::findOrFail($data['product_id']);

        $result = $this->inventory->transfer(
            $fromWarehouse,
            $toWarehouse,
            $product,
            (float) $data['quantity'],
            (float) ($data['unit_cost'] ?? 0),
            $data['notes'] ?? null,
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Stock transfer completed.',
            'data' => $result,
        ], 201);
    }
}
