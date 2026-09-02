<?php

namespace App\Http\Controllers\Api;

use App\Domain\Sales\Models\SalesInvoice;
use App\Domain\Sales\Models\SalesShipment;
use App\Domain\Sales\Services\SalesInvoiceService;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\OrganizationScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesInvoiceController extends Controller
{
    public function __construct(private readonly TenantContext $context,private readonly OrganizationScope $scope,private readonly SalesInvoiceService $service) {}
    public function index(): JsonResponse{return response()->json(['status'=>'success','data'=>$this->service->list()]);}
    public function store(Request $request): JsonResponse{$data=$request->validate(['sales_shipment_id'=>['required','uuid','exists:sales_shipments,id']]);$shipment=SalesShipment::query()->where('tenant_id',$this->context->tenantId())->where('company_id',$this->context->companyId())->where('branch_id',$this->context->branchId())->whereIn('warehouse_id',$this->scope->warehouseIds())->findOrFail($data['sales_shipment_id']);$invoice=$this->service->createFromShipment($shipment);return response()->json(['status'=>'success','message'=>$invoice->wasRecentlyCreated?'Sales invoice created.':'Existing sales invoice returned.','data'=>$invoice],$invoice->wasRecentlyCreated?201:200);}
    public function show(string $invoice): JsonResponse{$row=SalesInvoice::query()->with(['items.product','salesOrder','salesShipment','customer','creator'])->where('tenant_id',$this->context->tenantId())->where('company_id',$this->context->companyId())->where('branch_id',$this->context->branchId())->whereHas('salesShipment',fn($q)=>$q->whereIn('warehouse_id',$this->scope->warehouseIds()))->findOrFail($invoice);return response()->json(['status'=>'success','data'=>$row]);}
}
