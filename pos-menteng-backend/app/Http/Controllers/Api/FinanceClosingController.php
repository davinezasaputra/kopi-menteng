<?php

namespace App\Http\Controllers\Api;

use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Accounting\Services\FinanceClosingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceClosingController extends Controller
{
    public function __construct(private readonly FinanceClosingService $service) {}

    public function periods(): JsonResponse
    {
        return response()->json(['status'=>'success','data'=>$this->service->listPeriods()]);
    }

    public function storePeriod(Request $request): JsonResponse
    {
        $data=$request->validate([
            'year'=>['required','integer','min:2000','max:2100'],
            'month'=>['required','integer','between:1,12'],
            'notes'=>['nullable','string'],
        ]);

        return response()->json([
            'status'=>'success',
            'message'=>'Fiscal period created/opened.',
            'data'=>$this->service->createPeriod((int)$data['year'],(int)$data['month'],$data['notes']??null),
        ],201);
    }

    public function trialBalance(Request $request): JsonResponse
    {
        $data=$request->validate([
            'from'=>['nullable','date'],
            'to'=>['nullable','date','after_or_equal:from'],
        ]);

        return response()->json(['status'=>'success','data'=>$this->service->trialBalance($data['from']??null,$data['to']??null)]);
    }

    public function profitLoss(Request $request): JsonResponse
    {
        $data=$request->validate([
            'from'=>['nullable','date'],
            'to'=>['nullable','date','after_or_equal:from'],
        ]);

        return response()->json(['status'=>'success','data'=>$this->service->profitLoss($data['from']??null,$data['to']??null)]);
    }

    public function balanceSheet(Request $request): JsonResponse
    {
        $data=$request->validate(['to'=>['nullable','date']]);

        return response()->json(['status'=>'success','data'=>$this->service->balanceSheet($data['to']??null)]);
    }

    public function cashBook(Request $request): JsonResponse
    {
        $data=$request->validate([
            'account_code'=>['required','string','max:50'],
            'from'=>['nullable','date'],
            'to'=>['nullable','date','after_or_equal:from'],
        ]);

        return response()->json(['status'=>'success','data'=>$this->service->cashBook($data['account_code'],$data['from']??null,$data['to']??null)]);
    }

    public function reconcile(Request $request): JsonResponse
    {
        $data=$request->validate([
            'account_code'=>['required','string','max:50'],
            'statement_balance'=>['required','numeric','gte:0'],
            'adjustment_amount'=>['nullable','numeric'],
            'notes'=>['nullable','string'],
        ]);

        return response()->json([
            'status'=>'success',
            'message'=>'Cash reconciliation created.',
            'data'=>$this->service->reconcile($data['account_code'],(float)$data['statement_balance'],(float)($data['adjustment_amount']??0),$data['notes']??null),
        ],201);
    }

    public function closePeriod(Request $request,int $period): JsonResponse
    {
        return response()->json([
            'status'=>'success',
            'message'=>'Fiscal period closed.',
            'data'=>$this->service->closePeriod($period),
        ]);
    }

    public function reconciliations(): JsonResponse
    {
        $m=app(\App\Support\Tenancy\TenantContext::class)->membership();
        $rows=\App\Domain\Accounting\Models\CashReconciliation::query()
            ->where('tenant_id',$m->tenant_id)->where('company_id',$m->company_id)->where('branch_id',$m->branch_id)
            ->latest('reconciliation_date')->paginate(50);
        return response()->json(['status'=>'success','data'=>$rows]);
    }
}
