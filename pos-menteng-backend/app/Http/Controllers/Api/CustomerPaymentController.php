<?php
namespace App\Http\Controllers\Api;
use App\Domain\Sales\Models\{CustomerPayment,SalesInvoice};
use App\Domain\Sales\Services\CustomerPaymentService;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\OrganizationScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class CustomerPaymentController extends Controller{
 public function __construct(private readonly CustomerPaymentService $service,private readonly TenantContext $context,private readonly OrganizationScope $scope){}
 public function index():JsonResponse{return response()->json(['status'=>'success','data'=>$this->service->listScoped($this->context,$this->scope)]);}
 public function store(Request $request):JsonResponse{
  $d=$request->validate(['sales_invoice_id'=>['required','uuid','exists:sales_invoices,id'],'amount'=>['required','numeric','gt:0'],'method'=>['required','in:cash,bank'],'reference'=>['nullable','string','max:150'],'notes'=>['nullable','string']]);
  $invoice=SalesInvoice::query()->where('tenant_id',$this->context->tenantId())->where('company_id',$this->context->companyId())->where('branch_id',$this->context->branchId())->whereHas('salesShipment',fn($q)=>$q->whereIn('warehouse_id',$this->scope->warehouseIds()))->findOrFail($d['sales_invoice_id']);
  $p=$this->service->pay($invoice,(float)$d['amount'],$d['method'],$d['reference']??null,$d['notes']??null);
  return response()->json(['status'=>'success','message'=>'Customer payment posted.','data'=>$p],201);
 }
}
