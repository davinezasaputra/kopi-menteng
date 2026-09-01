<?php

namespace App\Http\Controllers\Api;

use App\Domain\Sales\Models\SalesApprovalMatrixRule;
use App\Domain\Sales\Models\SalesOrder;
use App\Domain\Sales\Services\SalesApprovalService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesApprovalController extends Controller
{
    public function __construct(private readonly SalesApprovalService $service)
    {
    }

    public function rules(): JsonResponse
    {
        return response()->json([
            'status'=>'success',
            'data'=>$this->service->listRules(),
        ]);
    }

    public function storeRule(Request $request): JsonResponse
    {
        $data=$request->validate([
            'approver_role_id'=>['required','integer','exists:roles,id'],
            'min_amount'=>['required','numeric','gte:0'],
            'max_amount'=>['nullable','numeric','gt:min_amount'],
            'priority'=>['nullable','integer','min:1'],
            'notes'=>['nullable','string'],
        ]);

        $rule=$this->service->createRule(
            (int)$data['approver_role_id'],
            (float)$data['min_amount'],
            isset($data['max_amount']) ? (float)$data['max_amount'] : null,
            (int)($data['priority'] ?? 1),
            $data['notes'] ?? null,
        );

        return response()->json([
            'status'=>'success',
            'message'=>'Sales approval rule created.',
            'data'=>$rule->load('approverRole'),
        ],201);
    }

    public function approve(int|string $order): JsonResponse
    {
        $row=SalesOrder::findOrFail($order);

        return response()->json([
            'status'=>'success',
            'message'=>'Sales order approved.',
            'data'=>$this->service->approve($row),
        ]);
    }

    public function reject(Request $request,int|string $order): JsonResponse
    {
        $data=$request->validate([
            'reason'=>['required','string','min:3'],
        ]);

        $row=SalesOrder::findOrFail($order);

        return response()->json([
            'status'=>'success',
            'message'=>'Sales order rejected.',
            'data'=>$this->service->reject($row,$data['reason']),
        ]);
    }
}
