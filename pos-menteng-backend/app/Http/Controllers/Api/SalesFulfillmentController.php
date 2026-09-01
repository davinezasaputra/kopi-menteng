<?php

namespace App\Http\Controllers\Api;

use App\Domain\Sales\Models\SalesFulfillment;
use App\Domain\Sales\Models\SalesOrder;
use App\Domain\Sales\Services\SalesFulfillmentService;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesFulfillmentController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly SalesFulfillmentService $service,
    ) {}

    public function index(): JsonResponse
    {
        $rows=SalesFulfillment::query()
            ->with(['salesOrder','warehouse','items.product','creator','picker','packer'])
            ->where('tenant_id',$this->context->tenantId())
            ->where('company_id',$this->context->companyId())
            ->where('branch_id',$this->context->branchId())
            ->latest('created_at')
            ->paginate(50);

        return response()->json(['status'=>'success','data'=>$rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $data=$request->validate([
            'sales_order_id'=>['required','uuid','exists:sales_orders,id'],
        ]);

        $order=SalesOrder::findOrFail($data['sales_order_id']);
        $fulfillment=$this->service->createFromApprovedOrder($order);

        return response()->json([
            'status'=>'success',
            'message'=>'Sales fulfillment created.',
            'data'=>$fulfillment,
        ],201);
    }

    public function show(string $fulfillment): JsonResponse
    {
        $row=SalesFulfillment::query()
            ->with(['salesOrder','warehouse','items.product','creator','picker','packer'])
            ->where('tenant_id',$this->context->tenantId())
            ->where('company_id',$this->context->companyId())
            ->where('branch_id',$this->context->branchId())
            ->findOrFail($fulfillment);

        return response()->json(['status'=>'success','data'=>$row]);
    }

    public function pick(Request $request,string $fulfillment): JsonResponse
    {
        $data=$request->validate([
            'items'=>['required','array','min:1'],
            'items.*.sales_fulfillment_item_id'=>['required','uuid'],
            'items.*.quantity'=>['required','numeric','gt:0'],
        ]);

        $row=SalesFulfillment::findOrFail($fulfillment);
        $result=$this->service->pick($row,$data['items']);

        return response()->json([
            'status'=>'success',
            'message'=>'Sales fulfillment picking updated.',
            'data'=>$result,
        ]);
    }

    public function pack(string $fulfillment): JsonResponse
    {
        $row=SalesFulfillment::findOrFail($fulfillment);
        $result=$this->service->pack($row);

        return response()->json([
            'status'=>'success',
            'message'=>'Sales fulfillment packed.',
            'data'=>$result,
        ]);
    }
}
