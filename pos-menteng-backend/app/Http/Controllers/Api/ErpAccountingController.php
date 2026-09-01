<?php

namespace App\Http\Controllers\Api;

use App\Domain\Accounting\Models\ErpAccount;
use App\Domain\Accounting\Models\ErpJournalBatch;
use App\Domain\Accounting\Services\ErpAccountingService;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ErpAccountingController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly ErpAccountingService $service,
    ) {}

    public function accounts(): JsonResponse
    {
        $rows = ErpAccount::query()
            ->where('tenant_id',$this->context->tenantId())
            ->where('company_id',$this->context->companyId())
            ->with('parent:id,code,name')
            ->orderBy('code')
            ->paginate(100);

        return response()->json(['status'=>'success','data'=>$rows]);
    }

    public function storeAccount(Request $request): JsonResponse
    {
        $data=$request->validate([
            'code'=>['required','string','max:50'],
            'name'=>['required','string','max:255'],
            'type'=>['required','in:asset,liability,equity,revenue,expense'],
            'normal_balance'=>['required','in:debit,credit'],
            'parent_id'=>['nullable','integer','exists:erp_accounts,id'],
            'is_postable'=>['nullable','boolean'],
            'is_active'=>['nullable','boolean'],
        ]);

        $account=$this->service->createAccount($data);

        return response()->json(['status'=>'success','data'=>$account],201);
    }

    public function journals(): JsonResponse
    {
        $rows = ErpJournalBatch::query()
            ->with('lines.account')
            ->where('tenant_id',$this->context->tenantId())
            ->where('company_id',$this->context->companyId())
            ->latest('id')
            ->paginate(100);

        return response()->json(['status'=>'success','data'=>$rows]);
    }

    public function storeJournal(Request $request): JsonResponse
    {
        $data=$request->validate([
            'branch_id'=>['nullable','integer','exists:branches,id'],
            'journal_number'=>['nullable','string','max:80'],
            'journal_date'=>['nullable','date'],
            'source_type'=>['nullable','string','max:50'],
            'source_id'=>['nullable','string','max:100'],
            'description'=>['required','string','max:255'],
            'lines'=>['required','array','min:2'],
            'lines.*.account_id'=>['required','integer','exists:erp_accounts,id'],
            'lines.*.debit'=>['nullable','numeric','gte:0'],
            'lines.*.credit'=>['nullable','numeric','gte:0'],
            'lines.*.description'=>['nullable','string','max:255'],
        ]);

        $journal=$this->service->postJournal($data);

        return response()->json(['status'=>'success','data'=>$journal],201);
    }
}
