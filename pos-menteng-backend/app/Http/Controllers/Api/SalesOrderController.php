<?php

namespace App\Http\Controllers\Api;

use App\Domain\Organization\Models\Warehouse;
use App\Domain\Sales\Models\SalesOrder;
use App\Domain\Sales\Services\SalesOrderService;
use App\Models\Customer;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesOrderController extends Controller
{
    public function __construct(private readonly SalesOrderService $service)
    {
    }

    public function index(): JsonResponse
    {
        $context=app(\App\Support\Tenancy\TenantContext::class);

        $rows=SalesOrder::query()
            ->with(['customer','warehouse','items.product','creator','submitter'])
            ->where('tenant_id',$context->tenantId())
            ->where('company_id',$context->companyId())
            ->where('branch_id',$context->branchId())
            ->latest('created_at')
            ->paginate(50);

        return response()->json(['status'=>'success','data'=>$rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $data=$request->validate([
            'warehouse_id'=>['required','integer','exists:warehouses,id'],
            'customer_id'=>['nullable','integer','exists:customers,id'],
            'discount_amount'=>['nullable','numeric','gte:0'],
            'tax_amount'=>['nullable','numeric','gte:0'],
            'notes'=>['nullable','string'],
            'items'=>['required','array','min:1'],
            'items.*.product_id'=>['required','exists:products,id'],
            'items.*.quantity'=>['required','numeric','gt:0'],
            'items.*.unit_price'=>['required','numeric','gte:0'],
            'items.*.discount_amount'=>['nullable','numeric','gte:0'],
            'items.*.tax_amount'=>['nullable','numeric','gte:0'],
            'items.*.notes'=>['nullable','string'],
        ]);

        $order=$this->service->create(
            Warehouse::findOrFail($data['warehouse_id']),
            $data['items'],
            isset($data['customer_id']) ? Customer::findOrFail($data['customer_id']) : null,
            $data['notes'] ?? null,
            (float)($data['discount_amount'] ?? 0),
            (float)($data['tax_amount'] ?? 0),
        );

        return response()->json(['status'=>'success','message'=>'Sales order created.','data'=>$order],201);
    }

    public function submit(int|string $order): JsonResponse
    {
        $row=SalesOrder::findOrFail($order);
        return response()->json(['status'=>'success','message'=>'Sales order submitted.','data'=>$this->service->submit($row)]);
    }

    public function cancel(int|string $order): JsonResponse
    {
        $row=SalesOrder::findOrFail($order);
        return response()->json(['status'=>'success','message'=>'Sales order cancelled.','data'=>$this->service->cancel($row)]);
    }
}
