<?php

namespace App\Http\Controllers\Api;

use App\Domain\Inventory\Services\InventoryValuationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Domain\Organization\Models\Warehouse;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class InventoryValuationController extends Controller
{
    public function __construct(
        private readonly InventoryValuationService $valuation,
        private readonly TenantContext $context,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'product_id' => ['nullable', 'exists:products,id'],
        ]);

        if (isset($data['warehouse_id'])) {
            $warehouse = Warehouse::with('branch')->findOrFail($data['warehouse_id']);

            if (
                (int) $warehouse->branch_id !== (int) $this->context->branchId()
                || (int) $warehouse->branch?->company_id !== (int) $this->context->companyId()
            ) {
                abort(404);
            }
        }

        if (isset($data['product_id'])) {
            $product = Product::findOrFail($data['product_id']);

            if ((int) $product->tenant_id !== (int) $this->context->tenantId()) {
                abort(404);
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->valuation->summary(
                isset($data['warehouse_id']) ? (int) $data['warehouse_id'] : null,
                $data['product_id'] ?? null,
            ),
        ]);
    }
}
