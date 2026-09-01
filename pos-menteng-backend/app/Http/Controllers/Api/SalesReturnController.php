<?php
namespace App\Http\Controllers\Api;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Sales\Models\{SalesInvoice,SalesReturn};
use App\Domain\Sales\Services\SalesReturnService;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class SalesReturnController extends Controller{
 public function __construct(private readonly TenantContext $context,private readonly SalesReturnService $service){}
 public function index():JsonResponse{ $m=$this->context->membership(); return response()->json(['status'=>'success','data'=>SalesReturn::with(['invoice','warehouse','items.product','customer'])->where('tenant_id',$m->tenant_id)->where('company_id',$m->company_id)->where('branch_id',$m->branch_id)->latest('return_date')->paginate(50)]);}
 public function store(Request $request):JsonResponse{
  $d=$request->validate(['sales_invoice_id'=>['required','uuid','exists:sales_invoices,id'],'warehouse_id'=>['required','integer','exists:warehouses,id'],'reason'=>['nullable','string'],'items'=>['required','array','min:1'],'items.*.product_id'=>['required','uuid'],'items.*.quantity'=>['required','numeric','gt:0']]);
  $r=$this->service->create(SalesInvoice::findOrFail($d['sales_invoice_id']),Warehouse::findOrFail($d['warehouse_id']),$d['items'],$d['reason']??null);
  return response()->json(['status'=>'success','message'=>'Sales return posted.','data'=>$r],201);
 }
}
