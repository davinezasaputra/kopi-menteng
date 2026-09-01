<?php

namespace App\Http\Controllers\Api;

use App\Domain\Sales\Models\SalesFulfillment;
use App\Domain\Sales\Models\SalesShipment;
use App\Domain\Sales\Services\SalesShipmentService;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesShipmentController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly SalesShipmentService $service,
    ) {}

    public function index(): JsonResponse
    {
        $rows=SalesShipment::query()
            ->with(['salesOrder','fulfillment','warehouse','inventoryReservation','shipper'])
            ->where('tenant_id',$this->context->tenantId())
            ->where('company_id',$this->context->companyId())
            ->where('branch_id',$this->context->branchId())
            ->latest('shipped_at')
            ->paginate(50);

        return response()->json(['status'=>'success','data'=>$rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $data=$request->validate([
            'sales_fulfillment_id'=>['required','uuid','exists:sales_fulfillments,id'],
            'carrier_name'=>['nullable','string','max:150'],
            'tracking_number'=>['nullable','string','max:150'],
            'notes'=>['nullable','string'],
        ]);

        $fulfillment=SalesFulfillment::findOrFail($data['sales_fulfillment_id']);
        $shipment=$this->service->ship(
            $fulfillment,
            $data['carrier_name'] ?? null,
            $data['tracking_number'] ?? null,
            $data['notes'] ?? null,
        );

        return response()->json([
            'status'=>'success',
            'message'=>$shipment->wasRecentlyCreated ? 'Shipment posted.' : 'Existing shipment returned for request ID.',
            'data'=>$shipment,
        ],$shipment->wasRecentlyCreated ? 201 : 200);
    }

    public function show(string $shipment): JsonResponse
    {
        $row=SalesShipment::query()
            ->with(['salesOrder','fulfillment.items.product','warehouse','inventoryReservation','shipper'])
            ->where('tenant_id',$this->context->tenantId())
            ->where('company_id',$this->context->companyId())
            ->where('branch_id',$this->context->branchId())
            ->findOrFail($shipment);

        return response()->json(['status'=>'success','data'=>$row]);
    }
}
