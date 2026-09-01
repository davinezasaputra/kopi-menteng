<?php

namespace App\Http\Controllers\Api;

use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Services\PurchasingReconciliationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PurchasingReconciliationController extends Controller
{
    public function __construct(private readonly PurchasingReconciliationService $service)
    {
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
