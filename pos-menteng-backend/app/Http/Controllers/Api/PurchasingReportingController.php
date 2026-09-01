<?php

namespace App\Http\Controllers\Api;

use App\Domain\Purchasing\Services\PurchasingReportingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchasingReportingController extends Controller
{
    public function __construct(private readonly PurchasingReportingService $service)
    {
    }

    public function dashboard(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable','date'],
            'to' => ['nullable','date','after_or_equal:from'],
        ]);

        return response()->json([
            'status'=>'success',
            'data'=>$this->service->dashboard($data['from'] ?? null, $data['to'] ?? null),
        ]);
    }


    public function supplierPerformance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable','date'],
            'to' => ['nullable','date','after_or_equal:from'],
        ]);

        return response()->json([
            'status'=>'success',
            'data'=>$this->service->supplierPerformance(
                $data['from'] ?? null,
                $data['to'] ?? null,
            ),
        ]);
    }

    public function apAging(): JsonResponse
    {
        return response()->json([
            'status'=>'success',
            'data'=>$this->service->apAging(),
        ]);
    }
}
