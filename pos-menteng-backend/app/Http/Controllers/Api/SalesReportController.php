<?php
namespace App\Http\Controllers\Api;
use App\Domain\Sales\Models\{SalesInvoice,SalesReturn};
use App\Domain\Sales\Models\SalesOrder;
use App\Domain\Sales\Models\SalesShipment;
use App\Domain\Sales\Services\SalesReceivableService;
use App\Domain\Accounting\Models\ErpJournalBatch;
use App\Support\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
class SalesReportController extends Controller{
 public function __construct(private readonly TenantContext $context,private readonly SalesReceivableService $receivables){}
 public function dashboard():JsonResponse{
  $m=$this->context->membership(); $base=fn($model)=>$model::query()->where('tenant_id',$m->tenant_id)->where('company_id',$m->company_id)->where('branch_id',$m->branch_id);
  $invoices=$base(SalesInvoice::class); $orders=$base(SalesOrder::class); $ship=$base(SalesShipment::class); $returns=$base(SalesReturn::class);
  $invoiceValue=(float)(clone $invoices)->sum('total_amount'); $paid=(float)(clone $invoices)->sum('paid_amount'); $returnValue=(float)(clone $returns)->sum('total_amount');
  return response()->json(['status'=>'success','data'=>[
   'sales_orders'=>['total'=>(clone $orders)->count(),'draft'=>(clone $orders)->where('status','draft')->count(),'submitted'=>(clone $orders)->where('status','submitted')->count(),'approved'=>(clone $orders)->where('status','approved')->count(),'fulfilled'=>(clone $orders)->where('status','fulfilled')->count(),'cancelled'=>(clone $orders)->where('status','cancelled')->count()],
   'shipments'=>['total'=>(clone $ship)->count()],
   'sales'=>['invoice_count'=>(clone $invoices)->count(),'gross_value'=>round($invoiceValue,2),'paid_value'=>round($paid,2),'outstanding'=>round(max(0,$invoiceValue-$paid),2),'return_value'=>round($returnValue,2),'net_value'=>round($invoiceValue-$returnValue,2)],
   'receivables'=>$this->receivables->aging(),
  ]]);
 }
 public function journals():JsonResponse{
  $m=$this->context->membership(); $rows=ErpJournalBatch::with('lines.account')->where('tenant_id',$m->tenant_id)->where('company_id',$m->company_id)->where('branch_id',$m->branch_id)->whereIn('source_type',['sales_invoice','customer_payment','sales_return','sales_return_cogs','sales_shipment'])->latest('journal_date')->paginate(50);
  return response()->json(['status'=>'success','data'=>$rows]);
 }
}
