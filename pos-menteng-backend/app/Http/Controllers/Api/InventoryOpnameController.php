<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Inventory\Models\InventoryOpname;
use App\Domain\Inventory\Services\StockOpnameService;
use App\Domain\Organization\Models\Warehouse;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryOpnameController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly StockOpnameService $service,
        private readonly AuditService $audit,
    ) {}

    public function index(): JsonResponse
    {
        $data = InventoryOpname::query()
            ->with(['warehouse', 'creator', 'approver'])
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->companyId())
            ->where('branch_id', $this->context->branchId())
            ->latest('id')
            ->paginate(50);

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['required', 'string', 'distinct'],
            'notes' => ['nullable', 'string'],
        ]);

        $warehouse = Warehouse::with('branch')->findOrFail($data['warehouse_id']);
        $opname = $this->service->create($warehouse, $data['product_ids'], $data['notes'] ?? null);

        $this->audit->record('created', 'inventory.opname', $opname, null, $opname->toArray());

        return response()->json(['status' => 'success', 'message' => 'Stock opname created.', 'data' => $opname], 201);
    }

    public function count(Request $request, InventoryOpname $opname): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer'],
            'items.*.counted_quantity' => ['required', 'numeric', 'gte:0'],
        ]);

        $updated = $this->service->count($opname, $data['items']);

        $this->audit->record('counted', 'inventory.opname', $updated, null, [
            'opname_id' => $updated->id,
            'items' => $data['items'],
        ]);

        return response()->json(['status' => 'success', 'message' => 'Stock count saved.', 'data' => $updated]);
    }

    public function show(InventoryOpname $opname): JsonResponse
    {
        $this->assertAccess($opname);
        return response()->json(['status' => 'success', 'data' => $opname->load(['items.product', 'warehouse', 'creator', 'approver', 'canceller'])]);
    }

    public function approve(InventoryOpname $opname): JsonResponse
    {
        try {
            $approved = $this->service->approve($opname);
        } catch (ValidationException $e) {
            throw $e;
        }

        $this->audit->record('approved', 'inventory.opname', $approved, null, $approved->toArray());

        return response()->json(['status' => 'success', 'message' => 'Stock opname approved.', 'data' => $approved]);
    }

    public function cancel(InventoryOpname $opname): JsonResponse
    {
        $cancelled = $this->service->cancel($opname);
        $this->audit->record('cancelled', 'inventory.opname', $cancelled, null, $cancelled->toArray());

        return response()->json(['status' => 'success', 'message' => 'Stock opname cancelled.', 'data' => $cancelled]);
    }

    private function assertAccess(InventoryOpname $opname): void
    {
        if ((int) $opname->tenant_id !== (int) $this->context->tenantId()
            || (int) $opname->company_id !== (int) $this->context->companyId()
            || (int) $opname->branch_id !== (int) $this->context->branchId()) {
            abort(404);
        }
    }
}
