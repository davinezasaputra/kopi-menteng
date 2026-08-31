<?php

namespace App\Http\Controllers\Api;

use App\Domain\Inventory\Services\InventoryValuationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryValuationController extends Controller
{
    public function __construct(private readonly InventoryValuationService $valuation)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'product_id' => ['nullable', 'exists:products,id'],
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $this->valuation->summary(
                isset($data['warehouse_id']) ? (int) $data['warehouse_id'] : null,
                $data['product_id'] ?? null,
            ),
        ]);
    }
}
