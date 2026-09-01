<?php
namespace App\Http\Controllers\Api;
use App\Domain\Sales\Models\{CustomerPayment,SalesInvoice};
use App\Domain\Sales\Services\CustomerPaymentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\Tenancy\TenantContext;
class CustomerPaymentController extends Controller{
 public function __construct(private readonly CustomerPaymentService $service){}
 public function index():JsonResponse{return response()->json(['status'=>'success','data'=>$this->service->list()]);}
 public function store(Request $request):JsonResponse{
  $d=$request->validate(['sales_invoice_id'=>['required','uuid','exists:sales_invoices,id'],'amount'=>['required','numeric','gt:0'],'method'=>['required','in:cash,bank'],'reference'=>['nullable','string','max:150'],'notes'=>['nullable','string']]);
  $p=$this->service->pay(SalesInvoice::findOrFail($d['sales_invoice_id']),(float)$d['amount'],$d['method'],$d['reference']??null,$d['notes']??null);
  return response()->json(['status'=>'success','message'=>'Customer payment posted.','data'=>$p],201);
 }
}
