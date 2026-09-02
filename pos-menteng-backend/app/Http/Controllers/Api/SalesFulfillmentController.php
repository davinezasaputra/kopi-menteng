<?php

namespace App\Http\Controllers\Api;

use App\Domain\Sales\Models\SalesFulfillment;
use App\Domain\Sales\Models\SalesOrder;
use App\Domain\Sales\Services\SalesFulfillmentService;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\OrganizationScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesFulfillmentController extends Controller
{
    public function __construct(private readonly TenantContext $context,private readonly OrganizationScope $scope,private readonly SalesFulfillmentService $service) {}
    private function order(int|string $id): SalesOrder{return SalesOrder::query()->where('tenant_id',$this->context->tenantId())->where('company_id',$this->context->companyId())->where('branch_id',$this->context->branchId())->whereIn('warehouse_id',$this->scope->warehouseIds())->findOrFail($id);}
    private function fulfillment(string $id): SalesFulfillment{return SalesFulfillment::query()->where('tenant_id',$this->context->tenantId())->where('company_id',$this->context->companyId())->where('branch_id',$this->context->branchId())->whereIn('warehouse_id',$this->scope->warehouseIds())->findOrFail($id);}
    public function index(): JsonResponse{$rows=SalesFulfillment::query()->with(['salesOrder','warehouse','items.product','creator','picker','packer'])->where('tenant_id',$this->context->tenantId())->where('company_id',$this->context->companyId())->where('branch_id',$this->context->branchId())->whereIn('warehouse_id',$this->scope->warehouseIds())->latest('created_at')->paginate(50);return response()->json(['status'=>'success','data'=>$rows]);}
    public function store(Request $request): JsonResponse{$data=$request->validate(['sales_order_id'=>['required','uuid','exists:sales_orders,id']]);$fulfillment=$this->service->createFromApprovedOrder($this->order($data['sales_order_id']));return response()->json(['status'=>'success','message'=>'Sales fulfillment created.','data'=>$fulfillment],201);}
    public function show(string $fulfillment): JsonResponse{return response()->json(['status'=>'success','data'=>$this->fulfillment($fulfillment)->load(['salesOrder','warehouse','items.product','creator','picker','packer'])]);}
    public function pick(Request $request,string $fulfillment): JsonResponse{$data=$request->validate(['items'=>['required','array','min:1'],'items.*.sales_fulfillment_item_id'=>['required','uuid'],'items.*.quantity'=>['required','numeric','gt:0']]);$result=$this->service->pick($this->fulfillment($fulfillment),$data['items']);return response()->json(['status'=>'success','message'=>'Sales fulfillment picking updated.','data'=>$result]);}
    public function pack(string $fulfillment): JsonResponse{return response()->json(['status'=>'success','message'=>'Sales fulfillment packed.','data'=>$this->service->pack($this->fulfillment($fulfillment))]);}
}
