<?php

namespace App\Http\Controllers\Api;

use App\Domain\Organization\Models\Warehouse;
use App\Domain\Purchasing\Models\PurchaseRequisition;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Services\PurchaseOrderService;
use App\Domain\Purchasing\Services\PurchaseRequisitionService;
use App\Support\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchasingController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly PurchaseRequisitionService $requisitions,
        private readonly PurchaseOrderService $orders,
    ) {}

    public function suppliers(): JsonResponse
    {
        $rows = Supplier::query()
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->companyId())
            ->orderBy('name')
            ->paginate(50);

        return response()->json(['status'=>'success','data'=>$rows]);
    }

    public function storeSupplier(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'=>['required','string','max:50'],
            'name'=>['required','string','max:255'],
            'tax_id'=>['nullable','string','max:100'],
            'contact_name'=>['nullable','string','max:150'],
            'phone'=>['nullable','string','max:50'],
            'email'=>['nullable','email','max:150'],
            'address'=>['nullable','string'],
            'payment_terms_days'=>['nullable','integer','min:0'],
            'status'=>['nullable','in:active,inactive'],
        ]);

        $data['tenant_id']=$this->context->tenantId();
        $data['company_id']=$this->context->companyId();
        $data['status']=$data['status'] ?? 'active';

        $supplier = Supplier::create($data);

        return response()->json(['status'=>'success','data'=>$supplier], 201);
    }

    public function requisitions(): JsonResponse
    {
        $rows = PurchaseRequisition::query()
            ->with(['warehouse','items.product','requester','submitter'])
            ->where('tenant_id',$this->context->tenantId())
            ->where('company_id',$this->context->companyId())
            ->where('branch_id',$this->context->branchId())
            ->latest('id')
            ->paginate(50);

        return response()->json(['status'=>'success','data'=>$rows]);
    }

    public function storeRequisition(Request $request): JsonResponse
    {
        $data=$request->validate([
            'warehouse_id'=>['required','integer','exists:warehouses,id'],
            'items'=>['required','array','min:1'],
            'items.*.product_id'=>['required','exists:products,id'],
            'items.*.quantity'=>['required','numeric','gt:0'],
            'items.*.estimated_unit_cost'=>['nullable','numeric','gte:0'],
            'items.*.notes'=>['nullable','string'],
            'needed_by'=>['nullable','date','after_or_equal:today'],
            'reason'=>['nullable','string'],
            'notes'=>['nullable','string'],
        ]);

        $requisition=$this->requisitions->create(
            Warehouse::findOrFail($data['warehouse_id']),
            $data['items'],
            $data['needed_by'] ?? null,
            $data['reason'] ?? null,
            $data['notes'] ?? null,
        );

        return response()->json(['status'=>'success','message'=>'Purchase requisition created.','data'=>$requisition],201);
    }

    public function submitRequisition(int $requisition): JsonResponse
    {
        $row=PurchaseRequisition::findOrFail($requisition);
        return response()->json(['status'=>'success','message'=>'Purchase requisition submitted.','data'=>$this->requisitions->submit($row)]);
    }

    public function cancelRequisition(int $requisition): JsonResponse
    {
        $row=PurchaseRequisition::findOrFail($requisition);
        return response()->json(['status'=>'success','message'=>'Purchase requisition cancelled.','data'=>$this->requisitions->cancel($row)]);
    }
}


