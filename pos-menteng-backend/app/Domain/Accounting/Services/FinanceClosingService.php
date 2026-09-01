<?php
namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Models\{CashReconciliation,ErpAccount,ErpJournalBatch,FiscalPeriod};
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class FinanceClosingService
{
    public function __construct(private readonly TenantContext $context) {}

    private function membership()
    {
        $m=$this->context->membership();
        if(!$m) throw ValidationException::withMessages(['context'=>'No active ERP context.']);
        return $m;
    }

    public function listPeriods(){ $m=$this->membership(); return FiscalPeriod::query()->where('tenant_id',$m->tenant_id)->where('company_id',$m->company_id)->orderByDesc('year')->orderByDesc('month')->get(); }

    public function createPeriod(int $year,int $month,?string $notes=null):FiscalPeriod
    {
        $m=$this->membership();
        if($month<1||$month>12) throw ValidationException::withMessages(['month'=>'Month must be between 1 and 12.']);
        $start=Carbon::create($year,$month,1)->startOfMonth();
        $end=$start->copy()->endOfMonth();

        $period=FiscalPeriod::query()
            ->where('tenant_id',$m->tenant_id)
            ->where('company_id',$m->company_id)
            ->where('year',$year)
            ->where('month',$month)
            ->first();

        if ($period?->status === 'closed') {
            throw ValidationException::withMessages(['period'=>'Fiscal period is already closed and cannot be reopened.']);
        }

        return FiscalPeriod::updateOrCreate(
            ['tenant_id'=>$m->tenant_id,'company_id'=>$m->company_id,'year'=>$year,'month'=>$month],
            ['starts_on'=>$start->toDateString(),'ends_on'=>$end->toDateString(),'status'=>'open','notes'=>$notes]
        );
    }

    public function assertOpenForDate(string $date,int $tenantId,int $companyId):FiscalPeriod
    {
        $d=Carbon::parse($date);
        $p=FiscalPeriod::query()->where('tenant_id',$tenantId)->where('company_id',$companyId)->where('year',$d->year)->where('month',$d->month)->first();
        if(!$p) throw ValidationException::withMessages(['journal_date'=>'No fiscal period exists for journal date.']);
        if($p->status!=='open') throw ValidationException::withMessages(['journal_date'=>'Fiscal period is closed.']);
        return $p;
    }

    public function trialBalance(?string $from=null,?string $to=null):array
    {
        $m=$this->membership();
        $rows=ErpAccount::query()->where('tenant_id',$m->tenant_id)->where('company_id',$m->company_id)->orderBy('code')->get();
        $result=[];
        foreach($rows as $account){
            $q=DB::table('erp_journal_lines as l')->join('erp_journal_batches as b','b.id','=','l.journal_batch_id')
              ->where('l.account_id',$account->id)->where('b.tenant_id',$m->tenant_id)->where('b.company_id',$m->company_id)->where('b.status','posted')
              ->when($from,fn($x)=>$x->whereDate('b.journal_date','>=',$from))->when($to,fn($x)=>$x->whereDate('b.journal_date','<=',$to));
            $debit=(float)$q->sum('l.debit'); $credit=(float)$q->sum('l.credit');
            if(abs($debit)+abs($credit)<0.0001) continue;
            $result[]=['account_id'=>$account->id,'code'=>$account->code,'name'=>$account->name,'type'=>$account->type,'debit'=>round($debit,2),'credit'=>round($credit,2),'balance'=>round($debit-$credit,2)];
        }
        return $result;
    }

    public function profitLoss(?string $from=null,?string $to=null):array
    {
        $tb=$this->trialBalance($from,$to);
        $revenue=round(array_sum(array_map(fn($x)=>$x['type']==='revenue'?$x['credit']-$x['debit']:0,$tb)),2);
        $expense=round(array_sum(array_map(fn($x)=>$x['type']==='expense'?$x['debit']-$x['credit']:0,$tb)),2);
        return ['revenue'=>$revenue,'expense'=>$expense,'net_profit'=>round($revenue-$expense,2),'accounts'=>$tb];
    }

    public function balanceSheet(?string $to=null):array
    {
        $tb=$this->trialBalance(null,$to);
        $assets=round(array_sum(array_map(fn($x)=>$x['type']==='asset'?$x['balance']:0,$tb)),2);
        $liabilities=round(array_sum(array_map(fn($x)=>$x['type']==='liability'?$x['credit']-$x['debit']:0,$tb)),2);
        $equity=round(array_sum(array_map(fn($x)=>$x['type']==='equity'?$x['credit']-$x['debit']:0,$tb)),2);
        $pl=$this->profitLoss(null,$to);
        $equityWithProfit=round($equity+$pl['net_profit'],2);
        return ['assets'=>$assets,'liabilities'=>$liabilities,'equity'=>$equityWithProfit,'balance_check'=>round($assets-$liabilities-$equityWithProfit,2),'accounts'=>$tb];
    }

    public function cashBook(string $accountCode,?string $from=null,?string $to=null):array
    {
        $m=$this->membership();
        $account=ErpAccount::where('tenant_id',$m->tenant_id)->where('company_id',$m->company_id)->where('code',$accountCode)->firstOrFail();
        $q=DB::table('erp_journal_lines as l')->join('erp_journal_batches as b','b.id','=','l.journal_batch_id')->where('l.account_id',$account->id)->where('b.tenant_id',$m->tenant_id)->where('b.company_id',$m->company_id)->where('b.status','posted')->when($from,fn($x)=>$x->whereDate('b.journal_date','>=',$from))->when($to,fn($x)=>$x->whereDate('b.journal_date','<=',$to));
        $balance=(float)$q->sum(DB::raw('l.debit-l.credit'));
        return ['account_code'=>$accountCode,'book_balance'=>round($balance,2)];
    }

    public function reconcile(string $accountCode,float $statementBalance,float $adjustmentAmount=0,?string $notes=null):CashReconciliation
    {
        $m=$this->membership();
        if($statementBalance<0) throw ValidationException::withMessages(['statement_balance'=>'Statement balance cannot be negative.']);
        $book=(float)$this->cashBook($accountCode)['book_balance'];
        $difference=round($statementBalance+$adjustmentAmount-$book,2);
        return CashReconciliation::create([
            'tenant_id'=>$m->tenant_id,'company_id'=>$m->company_id,'branch_id'=>$m->branch_id,
            'reconciliation_date'=>now()->toDateString(),'account_code'=>$accountCode,
            'book_balance'=>$book,'statement_balance'=>$statementBalance,'adjustment_amount'=>$adjustmentAmount,'difference'=>$difference,
            'status'=>'open','created_by'=>auth()->id(),'notes'=>$notes
        ]);
    }

    public function closePeriod(int $periodId):FiscalPeriod
    {
        $m=$this->membership();
        return DB::transaction(function()use($m,$periodId){
            $p=FiscalPeriod::where('tenant_id',$m->tenant_id)->where('company_id',$m->company_id)->lockForUpdate()->findOrFail($periodId);
            if($p->status==='closed') return $p;
            $unbalanced=ErpJournalBatch::query()->where('tenant_id',$m->tenant_id)->where('company_id',$m->company_id)->whereBetween('journal_date',[$p->starts_on,$p->ends_on])->where('status','posted')->whereRaw('ABS(total_debit-total_credit)>0.009')->exists();
            if($unbalanced) throw ValidationException::withMessages(['period'=>'Cannot close period with unbalanced journals.']);
            $p->status='closed'; $p->closed_by=auth()->id(); $p->closed_at=now(); $p->save();
            return $p->fresh();
        });
    }
}
