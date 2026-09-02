<?php

namespace App\Http\Controllers\Api;

use App\Domain\Organization\Models\Warehouse;
use App\Domain\Sales\Models\SalesOrder;
use App\Domain\Sales\Services\SalesOrderService;
use App\Models\Customer;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\OrganizationScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesOrderController extends Controller
{
    public function __construct(
        private readonly SalesOrderService $service,
        private readonly TenantContext $context,
        private readonly OrganizationScope $scope,
    ) {}

    private function scopedOrder(int|string $id): SalesOrder
    {
        return SalesOrder::query()
            ->where('tenant_id',$this->context->tenantId())
            ->where('company_id',$this->context->companyId())
            ->where('branch_id',$this->context->branchId())
            ->whereIn('warehouse_id',$this->scope->warehouseIds())
            ->findOrFail($id);
    }

    public function index(): JsonResponse
    {
        $rows=SalesOrder::query()
            ->with(['customer','warehouse','items.product','creator','submitter'])
            ->where('tenant_id',$this->context->tenantId())
            ->where('company_id',$this->context->companyId())
            ->where('branch_id',$this->context->branchId())
            ->whereIn('warehouse_id',$this->scope->warehouseIds())
            ->latest('created_at')->paginate(50);
        return response()->json(['status'=>'success','data'=>$rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $data=$request->validate([
            'warehouse_id'=>['required','integer','exists:warehouses,id'], 'customer_id'=>['nullable','integer','exists:customers,id'],
            'discount_amount'=>['nullable','numeric','gte:0'], 'tax_amount'=>['nullable','numeric','gte:0'], 'notes'=>['nullable','string'],
            'items'=>['required','array','min:1'], 'items.*.product_id'=>['required','exists:products,id'], 'items.*.quantity'=>['required','numeric','gt:0'],
            'items.*.unit_price'=>['required','numeric','gte:0'], 'items.*.discount_amount'=>['nullable','numeric','gte:0'], 'items.*.tax_amount'=>['nullable','numeric','gte:0'], 'items.*.notes'=>['nullable','string'],
        ]);
        $warehouse=$this->scope->requireWarehouse((int)$data['warehouse_id']);
        $customer=isset($data['customer_id']) ? Customer::query()->where('tenant_id',$this->context->tenantId())->findOrFail($data['customer_id']) : null;
        $order=$this->service->create($warehouse,$data['items'],$customer,$data['notes'] ?? null,(float)($data['discount_amount'] ?? 0),(float)($data['tax_amount'] ?? 0));
        if ($order->location_id !== $this->context->locationId()) { $order->location_id=$this->context->locationId(); $order->save(); }
        return response()->json(['status'=>'success','message'=>'Sales order created.','data'=>$order->fresh()],201);
    }

    public function submit(int|string $order): JsonResponse
    { return response()->json(['status'=>'success','message'=>'Sales order submitted.','data'=>$this->service->submit($this->scopedOrder($order))]); }
    public function cancel(int|string $order): JsonResponse
    { return response()->json(['status'=>'success','message'=>'Sales order cancelled.','data'=>$this->service->cancel($this->scopedOrder($order))]); }
}
