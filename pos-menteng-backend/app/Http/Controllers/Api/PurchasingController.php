<?php

namespace App\Http\Controllers\Api;

use App\Domain\Organization\Models\Warehouse;
use App\Domain\Purchasing\Models\PurchaseRequisition;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\GoodsReceipt;
use App\Domain\Purchasing\Models\SupplierInvoice;
use App\Domain\Purchasing\Models\SupplierPayment;
use App\Domain\Purchasing\Models\SupplierReturn;
use App\Domain\Purchasing\Models\SupplierCreditNote;
use App\Domain\Purchasing\Models\PurchasingBudget;
use App\Domain\Purchasing\Services\PurchasingBudgetService;
use App\Domain\Purchasing\Services\SupplierCreditNoteService;
use App\Domain\Purchasing\Services\SupplierReturnService;
use App\Domain\Purchasing\Services\AccountsPayableService;
use App\Domain\Purchasing\Services\GoodsReceiptService;
use App\Domain\Purchasing\Services\PurchaseOrderService;
use App\Domain\Purchasing\Services\PurchaseRequisitionService;
use App\Support\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchasingController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly PurchaseRequisitionService $requisitions,
        private readonly PurchaseOrderService $orders,
        private readonly GoodsReceiptService $goodsReceipts,
        private readonly AccountsPayableService $accountsPayable,
        private readonly SupplierReturnService $supplierReturns,
        private readonly SupplierCreditNoteService $creditNotes,
        private readonly PurchasingBudgetService $budget,
    ) {}

    public function suppliers(): JsonResponse
    {
        $rows = Supplier::query()
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->companyId())
            ->orderBy('name')
            ->paginate(50);

        return response()->json(['status'=>'success','data'=>$rows]);
    }

    public function storeSupplier(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'=>['required','string','max:50'],
            'name'=>['required','string','max:255'],
            'tax_id'=>['nullable','string','max:100'],
            'contact_name'=>['nullable','string','max:150'],
            'phone'=>['nullable','string','max:50'],
            'email'=>['nullable','email','max:150'],
            'address'=>['nullable','string'],
            'payment_terms_days'=>['nullable','integer','min:0'],
            'status'=>['nullable','in:active,inactive'],
        ]);

        $data['tenant_id']=$this->context->tenantId();
        $data['company_id']=$this->context->companyId();
        $data['status']=$data['status'] ?? 'active';

        $supplier = Supplier::create($data);

        return response()->json(['status'=>'success','data'=>$supplier], 201);
    }

    public function requisitions(): JsonResponse
    {
        $rows = PurchaseRequisition::query()
            ->with(['warehouse','items.product','requester','submitter'])
            ->where('tenant_id',$this->context->tenantId())
            ->where('company_id',$this->context->companyId())
            ->where('branch_id',$this->context->branchId())
            ->latest('id')
            ->paginate(50);

        return response()->json(['status'=>'success','data'=>$rows]);
    }

    public function storeRequisition(Request $request): JsonResponse
    {
        $data=$request->validate([
            'warehouse_id'=>['required','integer','exists:warehouses,id'],
            'items'=>['required','array','min:1'],
            'items.*.product_id'=>['required','exists:products,id'],
            'items.*.quantity'=>['required','numeric','gt:0'],
            'items.*.estimated_unit_cost'=>['nullable','numeric','gte:0'],
            'items.*.notes'=>['nullable','string'],
            'needed_by'=>['nullable','date','after_or_equal:today'],
            'reason'=>['nullable','string'],
            'notes'=>['nullable','string'],
        ]);

        $requisition=$this->requisitions->create(
            Warehouse::findOrFail($data['warehouse_id']),
            $data['items'],
            $data['needed_by'] ?? null,
            $data['reason'] ?? null,
            $data['notes'] ?? null,
        );

        return response()->json(['status'=>'success','message'=>'Purchase requisition created.','data'=>$requisition],201);
    }

    public function submitRequisition(int $requisition): JsonResponse
    {
        $row=PurchaseRequisition::findOrFail($requisition);
        return response()->json(['status'=>'success','message'=>'Purchase requisition submitted.','data'=>$this->requisitions->submit($row)]);
    }

    public function cancelRequisition(int $requisition): JsonResponse
    {
        $row=PurchaseRequisition::findOrFail($requisition);
        return response()->json(['status'=>'success','message'=>'Purchase requisition cancelled.','data'=>$this->requisitions->cancel($row)]);
    }
    public function purchaseOrders(): JsonResponse
    {
        $rows = PurchaseOrder::query()
            ->with(['supplier','warehouse','requisition','items.product','creator','submitter','approver'])
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->companyId())
            ->where('branch_id', $this->context->branchId())
            ->latest('id')
            ->paginate(50);

        return response()->json(['status'=>'success','data'=>$rows]);
    }

    public function storePurchaseOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required','integer','exists:suppliers,id'],
            'warehouse_id' => ['required','integer','exists:warehouses,id'],
            'purchase_requisition_id' => ['nullable','integer','exists:purchase_requisitions,id'],
            'expected_date' => ['nullable','date','after_or_equal:today'],
            'discount_amount' => ['nullable','numeric','gte:0'],
            'tax_amount' => ['nullable','numeric','gte:0'],
            'notes' => ['nullable','string'],
            'items' => ['required','array','min:1'],
            'items.*.product_id' => ['required','exists:products,id'],
            'items.*.quantity' => ['required','numeric','gt:0'],
            'items.*.unit_cost' => ['required','numeric','gte:0'],
            'items.*.discount_amount' => ['nullable','numeric','gte:0'],
            'items.*.tax_amount' => ['nullable','numeric','gte:0'],
            'items.*.notes' => ['nullable','string'],
        ]);

        $order = $this->orders->create(
            Supplier::findOrFail($data['supplier_id']),
            Warehouse::findOrFail($data['warehouse_id']),
            $data['items'],
            isset($data['purchase_requisition_id'])
                ? PurchaseRequisition::findOrFail($data['purchase_requisition_id'])
                : null,
            $data['expected_date'] ?? null,
            (float)($data['discount_amount'] ?? 0),
            (float)($data['tax_amount'] ?? 0),
            $data['notes'] ?? null,
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Purchase order created.',
            'data' => $order,
        ], 201);
    }

    public function submitPurchaseOrder(int $order): JsonResponse
    {
        $row = PurchaseOrder::findOrFail($order);

        return response()->json([
            'status' => 'success',
            'message' => 'Purchase order submitted.',
            'data' => $this->orders->submit($row),
        ]);
    }

    public function approvePurchaseOrder(int $order): JsonResponse
    {
        $row = PurchaseOrder::findOrFail($order);

        return response()->json([
            'status' => 'success',
            'message' => 'Purchase order approved.',
            'data' => $this->orders->approve($row),
        ]);
    }

    public function goodsReceipts(): JsonResponse
    {
        $rows = GoodsReceipt::query()
            ->with(['supplier','warehouse','order','items.product'])
            ->where('tenant_id',$this->context->tenantId())
            ->where('company_id',$this->context->companyId())
            ->where('branch_id',$this->context->branchId())
            ->latest('id')
            ->paginate(50);

        return response()->json(['status'=>'success','data'=>$rows]);
    }

    public function storeGoodsReceipt(Request $request): JsonResponse
    {
        $data = $request->validate([
            'purchase_order_id'=>['required','integer','exists:purchase_orders,id'],
            'warehouse_id'=>['required','integer','exists:warehouses,id'],
            'notes'=>['nullable','string'],
            'items'=>['required','array','min:1'],
            'items.*.purchase_order_item_id'=>['required','integer','exists:purchase_order_items,id'],
            'items.*.quantity'=>['required','numeric','gt:0'],
            'items.*.unit_cost'=>['required','numeric','gt:0'],
        ]);

        $receipt=$this->goodsReceipts->receive(
            PurchaseOrder::findOrFail($data['purchase_order_id']),
            Warehouse::findOrFail($data['warehouse_id']),
            $data['items'],
            $data['notes'] ?? null,
        );

        return response()->json([
            'status'=>'success',
            'message'=>'Goods receipt posted.',
            'data'=>$receipt,
        ],201);
    }

    public function supplierInvoices(): JsonResponse
    {
        $rows = SupplierInvoice::query()
            ->with(['supplier','order','goodsReceipt','creator'])
            ->where('tenant_id',$this->context->tenantId())
            ->where('company_id',$this->context->companyId())
            ->where('branch_id',$this->context->branchId())
            ->latest('id')
            ->paginate(50);

        return response()->json(['status'=>'success','data'=>$rows]);
    }

    public function storeSupplierInvoice(Request $request): JsonResponse
    {
        $data=$request->validate([
            'goods_receipt_id'=>['required','integer','exists:goods_receipts,id'],
            'invoice_number'=>['required','string','max:100'],
            'invoice_date'=>['nullable','date'],
            'due_date'=>['nullable','date','after_or_equal:invoice_date'],
            'notes'=>['nullable','string'],
        ]);

        $invoice=$this->accountsPayable->createInvoice(
            GoodsReceipt::findOrFail($data['goods_receipt_id']),
            $data['invoice_number'],
            $data['invoice_date'] ?? null,
            $data['due_date'] ?? null,
            $data['notes'] ?? null,
        );

        return response()->json(['status'=>'success','message'=>'Supplier invoice created.','data'=>$invoice],201);
    }

    public function supplierPayments(): JsonResponse
    {
        $rows = SupplierPayment::query()
            ->with(['supplier','invoice','payer'])
            ->where('tenant_id',$this->context->tenantId())
            ->where('company_id',$this->context->companyId())
            ->where('branch_id',$this->context->branchId())
            ->latest('id')
            ->paginate(50);

        return response()->json(['status'=>'success','data'=>$rows]);
    }

    public function storeSupplierPayment(Request $request): JsonResponse
    {
        $data=$request->validate([
            'supplier_invoice_id'=>['required','integer','exists:supplier_invoices,id'],
            'amount'=>['required','numeric','gt:0'],
            'method'=>['nullable','in:cash,bank_transfer,giro,other'],
            'reference'=>['nullable','string','max:180'],
            'notes'=>['nullable','string'],
        ]);

        $payment=$this->accountsPayable->pay(
            SupplierInvoice::findOrFail($data['supplier_invoice_id']),
            (float)$data['amount'],
            $data['method'] ?? 'bank_transfer',
            $data['reference'] ?? null,
            $data['notes'] ?? null,
        );

        return response()->json(['status'=>'success','message'=>'Supplier payment recorded.','data'=>$payment],201);
    }

    public function purchasingBudget(Request $request): JsonResponse
    {
        return response()->json([
            'status'=>'success',
            'data'=>$this->budget->summary($request->query('year')),
        ]);
    }

    public function storePurchasingBudget(Request $request): JsonResponse
    {
        $data=$request->validate([
            'budget_year'=>['required','integer','min:2000','max:2100'],
            'allocated_amount'=>['required','numeric','gte:0'],
            'notes'=>['nullable','string'],
        ]);

        $budget=$this->budget->createOrUpdate(
            (int)$data['budget_year'],
            (float)$data['allocated_amount'],
            $data['notes'] ?? null,
        );

        return response()->json(['status'=>'success','message'=>'Purchasing budget saved.','data'=>$budget],201);
    }

    public function supplierReturns(): JsonResponse
    {
        $rows = SupplierReturn::query()
            ->with(['supplier','warehouse','order','goodsReceipt','items.product'])
            ->where('tenant_id',$this->context->tenantId())
            ->where('company_id',$this->context->companyId())
            ->where('branch_id',$this->context->branchId())
            ->latest('id')
            ->paginate(50);

        return response()->json(['status'=>'success','data'=>$rows]);
    }

    public function storeSupplierReturn(Request $request): JsonResponse
    {
        $data=$request->validate([
            'purchase_order_id'=>['required','integer','exists:purchase_orders,id'],
            'goods_receipt_id'=>['required','integer','exists:goods_receipts,id'],
            'warehouse_id'=>['required','integer','exists:warehouses,id'],
            'reason'=>['nullable','string'],
            'notes'=>['nullable','string'],
            'items'=>['required','array','min:1'],
            'items.*.goods_receipt_item_id'=>['required','integer','exists:goods_receipt_items,id'],
            'items.*.quantity'=>['required','numeric','gt:0'],
        ]);

        $return=$this->supplierReturns->createAndPost(
            PurchaseOrder::findOrFail($data['purchase_order_id']),
            GoodsReceipt::findOrFail($data['goods_receipt_id']),
            Warehouse::findOrFail($data['warehouse_id']),
            $data['items'],
            $data['reason'] ?? null,
            $data['notes'] ?? null,
        );

        return response()->json([
            'status'=>'success',
            'message'=>'Supplier return posted.',
            'data'=>$return,
        ],201);
    }

    public function supplierCreditNotes(): JsonResponse
    {
        $rows = SupplierCreditNote::query()
            ->with(['supplier','supplierReturn','supplierInvoice','creator'])
            ->where('tenant_id',$this->context->tenantId())
            ->where('company_id',$this->context->companyId())
            ->where('branch_id',$this->context->branchId())
            ->latest('id')
            ->paginate(50);

        return response()->json(['status'=>'success','data'=>$rows]);
    }

    public function storeSupplierCreditNote(Request $request): JsonResponse
    {
        $data=$request->validate([
            'supplier_return_id'=>['required','integer','exists:supplier_returns,id'],
            'supplier_invoice_id'=>['nullable','integer','exists:supplier_invoices,id'],
            'credit_note_number'=>['required','string','max:100'],
            'reason'=>['nullable','string'],
            'notes'=>['nullable','string'],
        ]);

        $note=$this->creditNotes->createFromReturn(
            SupplierReturn::findOrFail($data['supplier_return_id']),
            isset($data['supplier_invoice_id']) ? SupplierInvoice::findOrFail($data['supplier_invoice_id']) : null,
            $data['credit_note_number'],
            $data['reason'] ?? null,
            $data['notes'] ?? null,
        );

        return response()->json([
            'status'=>'success',
            'message'=>'Supplier credit note created.',
            'data'=>$note,
        ],201);
    }

    public function cancelPurchaseOrder(int $order): JsonResponse
    {
        $row = PurchaseOrder::findOrFail($order);

        return response()->json([
            'status' => 'success',
            'message' => 'Purchase order cancelled.',
            'data' => $this->orders->cancel($row),
        ]);
    }
}
