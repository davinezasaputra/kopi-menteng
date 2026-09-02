<?php

namespace App\Http\Controllers\Api;

use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Services\PurchasingReconciliationService;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PurchasingReconciliationController extends Controller
{
    public function __construct(private readonly PurchasingReconciliationService $service)
    {
    }

    public function index(): JsonResponse
    {
        $context = app(TenantContext::class);
        $tenantId = $context->tenantId();
        $companyId = $context->companyId();
        $branchId = $context->branchId();

        $orders = PurchaseOrder::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->latest('id')
            ->paginate(50);

        $orders->setCollection($orders->getCollection()->map(function (PurchaseOrder $order) {
            $result = $this->service->reconcile($order);

            return [
                'id' => $order->id,
                'order_number' => $result['purchase_order']['order_number'],
                'status' => $result['purchase_order']['status'],
                'ordered_quantity' => $result['purchase_order']['ordered_quantity'],
                'received_quantity' => $result['purchase_order']['received_quantity'],
                'remaining_quantity' => $result['purchase_order']['remaining_quantity'],
                'receipt_value' => $result['goods_receipt']['received_value'],
                'invoice_value' => $result['supplier_invoice']['invoice_value'],
                'paid_amount' => $result['supplier_invoice']['paid_amount'],
                'outstanding' => $result['supplier_invoice']['outstanding'],
                'journal_count' => $result['accounting']['journal_count'],
                'accounting_balanced' => $result['accounting']['balanced'],
                'reconciled' => $result['reconciled'],
            ];
        }));

        return response()->json([
            'status' => 'success',
            'data' => $orders,
        ]);
    }

    public function show(int $order): JsonResponse
    {
        $purchaseOrder = PurchaseOrder::findOrFail($order);

        return response()->json([
            'status' => 'success',
            'data' => $this->service->reconcile($purchaseOrder),
        ]);
    }
}
