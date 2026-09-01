<?php
namespace App\Http\Controllers\Api;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Sales\Models\{SalesInvoice,SalesReturn};
use App\Domain\Sales\Services\SalesReturnService;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\OrganizationScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class SalesReturnController extends Controller{
 public function __construct(private readonly TenantContext $context,private readonly OrganizationScope $scope,private readonly SalesReturnService $service){}
 public function index():JsonResponse{return response()->json(['status'=>'success','data'=>SalesReturn::with(['invoice','warehouse','items.product','customer'])->where('tenant_id',$this->context->tenantId())->where('company_id',$this->context->companyId())->where('branch_id',$this->context->branchId())->whereIn('warehouse_id',$this->scope->warehouseIds())->latest('return_date')->paginate(50)]);}
 public function store(Request $request):JsonResponse{$d=$request->validate(['sales_invoice_id'=>['required','uuid','exists:sales_invoices,id'],'warehouse_id'=>['required','integer','exists:warehouses,id'],'reason'=>['nullable','string'],'items'=>['required','array','min:1'],'items.*.product_id'=>['required','uuid'],'items.*.quantity'=>['required','numeric','gt:0']]);$invoice=SalesInvoice::query()->where('tenant_id',$this->context->tenantId())->where('company_id',$this->context->companyId())->where('branch_id',$this->context->branchId())->whereHas('salesShipment',fn($q)=>$q->whereIn('warehouse_id',$this->scope->warehouseIds()))->findOrFail($d['sales_invoice_id']);$warehouse=$this->scope->requireWarehouse((int)$d['warehouse_id']);$r=$this->service->create($invoice,$warehouse,$d['items'],$d['reason']??null);return response()->json(['status'=>'success','message'=>'Sales return posted.','data'=>$r],201);}
}
