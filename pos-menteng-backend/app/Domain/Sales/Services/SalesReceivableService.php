<?php
namespace App\Domain\Sales\Services;

use App\Domain\Sales\Models\SalesInvoice;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

class SalesReceivableService
{
    public function __construct(private readonly TenantContext $context) {}
    public function invoices()
    {
        $m=$this->context->membership();
        if(!$m) throw ValidationException::withMessages(['context'=>'No active ERP context.']);
        return SalesInvoice::query()->with(['customer','salesOrder','salesShipment'])
            ->where('tenant_id',$m->tenant_id)->where('company_id',$m->company_id)->where('branch_id',$m->branch_id)
            ->orderByDesc('invoice_date')->orderByDesc('created_at')->paginate(50);
    }
    public function aging(): array
    {
        $m=$this->context->membership();
        if(!$m) throw ValidationException::withMessages(['context'=>'No active ERP context.']);
        $rows=SalesInvoice::query()->where('tenant_id',$m->tenant_id)->where('company_id',$m->company_id)->where('branch_id',$m->branch_id)->get();
        $b=['current'=>0.0,'1_30'=>0.0,'31_60'=>0.0,'61_90'=>0.0,'91_plus'=>0.0];
        foreach($rows as $i){
            $o=max(0,(float)$i->outstanding_amount);
            if($o<=0) continue;
            if(!$i->due_date || $i->due_date->isFuture()){ $b['current']+=$o; continue; }
            $d=$i->due_date->diffInDays(now());
            $k=match(true){$d<=30=>'1_30',$d<=60=>'31_60',$d<=90=>'61_90',default=>'91_plus'};
            $b[$k]+=$o;
        }
        $b=array_map(fn($v)=>round($v,2),$b);
        return $b+['total_outstanding'=>round(array_sum($b),2)];
    }
}
