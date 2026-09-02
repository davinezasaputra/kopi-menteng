<?php

namespace App\Http\Controllers\Api;

use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Services\InventoryService;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\Tenancy\OrganizationScope;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly OrganizationScope $scope,
        private readonly InventoryService $inventory,
    ) {
    }

    public function balances(Request $request): JsonResponse
    {
        $query = InventoryBalance::query()
            ->with(['warehouse', 'product'])
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->companyId())
            ->where('branch_id', $this->context->branchId())
            ->whereIn('warehouse_id', $this->scope->warehouseQuery()->select('warehouses.id'));

        if ($request->filled('warehouse_id')) $query->where('warehouse_id', $request->integer('warehouse_id'));
        if ($request->filled('product_id')) $query->where('product_id', $request->string('product_id')->toString());

        return response()->json(['status' => 'success', 'data' => $query->orderBy('id')->paginate(50)]);
    }

    public function movements(Request $request): JsonResponse
    {
        $query = StockMovement::query()
            ->with(['warehouse', 'product', 'creator'])
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->companyId())
            ->where('branch_id', $this->context->branchId())
            ->whereIn('warehouse_id', $this->scope->warehouseQuery()->select('warehouses.id'));

        if ($request->filled('warehouse_id')) $query->where('warehouse_id', $request->integer('warehouse_id'));
        if ($request->filled('product_id')) $query->where('product_id', $request->string('product_id')->toString());
        if ($request->filled('movement_type')) $query->where('movement_type', $request->string('movement_type')->toString());

        return response()->json(['status' => 'success', 'data' => $query->latest('id')->paginate(50)]);
    }

    public function receive(Request $request): JsonResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required','integer','exists:warehouses,id'], 'product_id' => ['required','exists:products,id'],
            'quantity' => ['required','numeric','gt:0'], 'unit_cost' => ['required','numeric','gte:0'],
            'reference_type' => ['nullable','string','max:180'], 'reference_id' => ['nullable','string','max:100'], 'notes' => ['nullable','string'],
        ]);
        $warehouse = $this->scope->requireWarehouse((int) $data['warehouse_id']);
        $product = Product::query()->where('tenant_id', $this->context->tenantId())->findOrFail($data['product_id']);
        $balance = $this->inventory->receive($warehouse, $product, (float) $data['quantity'], (float) $data['unit_cost'], $data['reference_type'] ?? null, $data['reference_id'] ?? null, $data['notes'] ?? null);
        return response()->json(['status'=>'success','message'=>'Stock received.','data'=>$balance], 201);
    }

    public function issue(Request $request): JsonResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required','integer','exists:warehouses,id'], 'product_id' => ['required','exists:products,id'],
            'quantity' => ['required','numeric','gt:0'], 'reference_type' => ['nullable','string','max:180'], 'reference_id' => ['nullable','string','max:100'], 'notes' => ['nullable','string'],
        ]);
        $warehouse = $this->scope->requireWarehouse((int) $data['warehouse_id']);
        $product = Product::query()->where('tenant_id', $this->context->tenantId())->findOrFail($data['product_id']);
        $balance = $this->inventory->issue($warehouse, $product, (float) $data['quantity'], $data['reference_type'] ?? null, $data['reference_id'] ?? null, $data['notes'] ?? null);
        return response()->json(['status'=>'success','message'=>'Stock issued.','data'=>$balance]);
    }

    public function adjust(Request $request): JsonResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required','integer','exists:warehouses,id'], 'product_id' => ['required','exists:products,id'],
            'quantity' => ['required','numeric','not_in:0'], 'unit_cost' => ['nullable','numeric','gte:0'], 'notes' => ['nullable','string'],
        ]);
        $warehouse = $this->scope->requireWarehouse((int) $data['warehouse_id']);
        $product = Product::query()->where('tenant_id', $this->context->tenantId())->findOrFail($data['product_id']);
        $balance = $this->inventory->adjust($warehouse, $product, (float) $data['quantity'], (float) ($data['unit_cost'] ?? 0), $data['notes'] ?? null);
        return response()->json(['status'=>'success','message'=>'Stock adjusted.','data'=>$balance]);
    }
}
