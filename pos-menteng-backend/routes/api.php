<?php

use App\Http\Controllers\Api\AccountingController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoriesController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\HrmController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\InventoryReservationController;
use App\Http\Controllers\Api\InventoryOpnameController;
use App\Http\Controllers\Api\InventoryTransferController;
use App\Http\Controllers\Api\InventoryValuationController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrganizationProvisioningController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PurchasingController;
use App\Http\Controllers\Api\PurchasingReconciliationController;
use App\Http\Controllers\Api\PurchasingReportingController;
use App\Http\Controllers\Api\ErpAccountingController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RawMaterialController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\UserController;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('request.id')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/login-pin', [AuthController::class, 'loginPin']);
    Route::post('/midtrans/webhook', [PaymentController::class, 'handleWebhook']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', function (Request $request) {
            $user = $request->user();
            $context = app(TenantContext::class);
            return response()->json([
                'user' => $user,
                'context' => [
                    'tenant_id' => $context->tenantId(),
                    'company_id' => $context->companyId(),
                    'branch_id' => $context->branchId(),
                    'role' => $context->membership()?->role?->code,
                ],
            ]);
        })->middleware('tenant');

        Route::prefix('platform/organization')->middleware('platform.admin')->group(function () {
            Route::post('/tenants', [OrganizationProvisioningController::class, 'storeTenant']);
            Route::post('/companies', [OrganizationProvisioningController::class, 'storeCompany']);
            Route::post('/branches', [OrganizationProvisioningController::class, 'storeBranch']);
            Route::post('/warehouses', [OrganizationProvisioningController::class, 'storeWarehouse']);
            Route::post('/tenant-admins', [OrganizationProvisioningController::class, 'storeTenantAdmin']);
            Route::get('/tenants/{tenant}', [OrganizationProvisioningController::class, 'showTenant']);
        });

        Route::middleware(['tenant', 'permission:users.user.view'])->group(function () { Route::get('/users', [UserController::class, 'index']); });
        Route::middleware(['tenant', 'permission:users.user.create'])->group(function () { Route::post('/users', [UserController::class, 'store']); });
        Route::middleware(['tenant', 'permission:users.user.delete'])->group(function () { Route::delete('/users/{id}', [UserController::class, 'destroy']); });
        Route::middleware(['tenant', 'permission:audit.audit_log.view'])->group(function () { Route::get('/audit-logs', [AuditLogController::class, 'index']); });

        Route::middleware('tenant')->group(function () {
            Route::get('/shifts/status', [ShiftController::class, 'status']);
            Route::post('/shifts/open', [ShiftController::class, 'open']);
            Route::post('/shifts/close', [ShiftController::class, 'close']);
            Route::get('/products', [ProductController::class, 'index']);
            Route::post('/products', [ProductController::class, 'store']);
            Route::put('/products/{id}', [ProductController::class, 'update']);
            Route::delete('/products/{id}', [ProductController::class, 'destroy']);
            Route::post('/products/{id}/recipe', [ProductController::class, 'syncRecipe']);
            Route::get('/categories', [CategoriesController::class, 'index']);
            Route::get('/orders', [OrderController::class, 'index']);
            Route::get('/orders/history', [OrderController::class, 'history']);
            Route::post('/orders/checkout', [OrderController::class, 'checkout']);

            Route::get('/inventory/balances', [InventoryController::class, 'balances'])->middleware('permission:inventory.stock.view');
            Route::get('/inventory/movements', [InventoryController::class, 'movements'])->middleware('permission:inventory.stock.view');
            Route::get('/inventory/valuation', [InventoryValuationController::class, 'index'])->middleware('permission:inventory.stock.view');

            Route::get('/purchasing/suppliers', [PurchasingController::class, 'suppliers'])->middleware('permission:purchasing.supplier.view');
            Route::post('/purchasing/suppliers', [PurchasingController::class, 'storeSupplier'])->middleware('permission:purchasing.supplier.create');
            Route::get('/purchasing/requisitions', [PurchasingController::class, 'requisitions'])->middleware('permission:purchasing.requisition.view');
            Route::post('/purchasing/requisitions', [PurchasingController::class, 'storeRequisition'])->middleware('permission:purchasing.requisition.create');
            Route::post('/purchasing/requisitions/{requisition}/submit', [PurchasingController::class, 'submitRequisition'])->middleware('permission:purchasing.requisition.submit');
            Route::post('/purchasing/requisitions/{requisition}/cancel', [PurchasingController::class, 'cancelRequisition'])->middleware('permission:purchasing.requisition.cancel');
            Route::get('/purchasing/orders', [PurchasingController::class, 'purchaseOrders'])->middleware('permission:purchasing.order.view');
            Route::post('/purchasing/orders', [PurchasingController::class, 'storePurchaseOrder'])->middleware('permission:purchasing.order.create');
            Route::post('/purchasing/orders/{order}/submit', [PurchasingController::class, 'submitPurchaseOrder'])->middleware('permission:purchasing.order.submit');
            Route::post('/purchasing/orders/{order}/approve', [PurchasingController::class, 'approvePurchaseOrder'])->middleware('permission:purchasing.order.approve');
            Route::post('/purchasing/orders/{order}/cancel', [PurchasingController::class, 'cancelPurchaseOrder'])->middleware('permission:purchasing.order.cancel');
            Route::get('/purchasing/goods-receipts', [PurchasingController::class, 'goodsReceipts'])->middleware('permission:purchasing.receipt.view');
            Route::post('/purchasing/goods-receipts', [PurchasingController::class, 'storeGoodsReceipt'])->middleware('permission:purchasing.receipt.create');
            Route::get('/purchasing/invoices', [PurchasingController::class, 'supplierInvoices'])->middleware('permission:purchasing.ap.view');
            Route::post('/purchasing/invoices', [PurchasingController::class, 'storeSupplierInvoice'])->middleware('permission:purchasing.ap.create');
            Route::get('/purchasing/payments', [PurchasingController::class, 'supplierPayments'])->middleware('permission:purchasing.ap.view');
            Route::post('/purchasing/payments', [PurchasingController::class, 'storeSupplierPayment'])->middleware('permission:purchasing.ap.pay');
            Route::get('/purchasing/reconciliation/orders/{order}', [PurchasingReconciliationController::class, 'show'])->middleware('permission:purchasing.reconciliation.view');
            Route::get('/purchasing/reports/dashboard', [PurchasingReportingController::class, 'dashboard'])->middleware('permission:purchasing.reporting.view');
            Route::get('/purchasing/reports/ap-aging', [PurchasingReportingController::class, 'apAging'])->middleware('permission:purchasing.reporting.view');
            Route::get('/purchasing/returns', [PurchasingController::class, 'supplierReturns'])->middleware('permission:purchasing.return.view');
            Route::post('/purchasing/returns', [PurchasingController::class, 'storeSupplierReturn'])->middleware('permission:purchasing.return.create');
            Route::get('/purchasing/credit-notes', [PurchasingController::class, 'supplierCreditNotes'])->middleware('permission:purchasing.credit_note.view');
            Route::post('/purchasing/credit-notes', [PurchasingController::class, 'storeSupplierCreditNote'])->middleware('permission:purchasing.credit_note.create');
            Route::get('/erp/accounting/accounts', [ErpAccountingController::class, 'accounts'])->middleware('permission:accounting.erp.account.view');
            Route::post('/erp/accounting/accounts', [ErpAccountingController::class, 'storeAccount'])->middleware('permission:accounting.erp.account.create');
            Route::get('/erp/accounting/journals', [ErpAccountingController::class, 'journals'])->middleware('permission:accounting.erp.journal.view');
            Route::post('/erp/accounting/journals', [ErpAccountingController::class, 'storeJournal'])->middleware('permission:accounting.erp.journal.create');
            Route::post('/inventory/receive', [InventoryController::class, 'receive'])->middleware('permission:inventory.stock.adjust');
            Route::post('/inventory/issue', [InventoryController::class, 'issue'])->middleware('permission:inventory.stock.adjust');
            Route::post('/inventory/adjust', [InventoryController::class, 'adjust'])->middleware('permission:inventory.stock.adjust');
            Route::get('/inventory/opnames', [InventoryOpnameController::class, 'index'])->middleware('permission:inventory.stock.view');
            Route::post('/inventory/opnames', [InventoryOpnameController::class, 'store'])->middleware('permission:inventory.stock.adjust');
            Route::get('/inventory/opnames/{opname}', [InventoryOpnameController::class, 'show'])->middleware('permission:inventory.stock.view');
            Route::post('/inventory/opnames/{opname}/count', [InventoryOpnameController::class, 'count'])->middleware('permission:inventory.stock.adjust');
            Route::post('/inventory/opnames/{opname}/approve', [InventoryOpnameController::class, 'approve'])->middleware('permission:inventory.stock.adjust');
            Route::post('/inventory/opnames/{opname}/cancel', [InventoryOpnameController::class, 'cancel'])->middleware('permission:inventory.stock.adjust');
            Route::get('/inventory/transfers', [InventoryTransferController::class, 'index'])->middleware('permission:inventory.stock.view');
            Route::post('/inventory/transfers', [InventoryTransferController::class, 'store'])->middleware('permission:inventory.stock.adjust');
            Route::get('/inventory/reservations', [InventoryReservationController::class, 'index'])->middleware('permission:inventory.stock.view');
            Route::post('/inventory/reservations', [InventoryReservationController::class, 'store'])->middleware('permission:inventory.stock.adjust');
            Route::get('/inventory/reservations/{reservation}', [InventoryReservationController::class, 'show'])->middleware('permission:inventory.stock.view');
            Route::post('/inventory/reservations/{reservation}/release', [InventoryReservationController::class, 'release'])->middleware('permission:inventory.stock.adjust');
            Route::post('/inventory/reservations/{reservation}/expire', [InventoryReservationController::class, 'expire'])->middleware('permission:inventory.stock.adjust');
            Route::post('/inventory/reservations/{reservation}/fulfill', [InventoryReservationController::class, 'fulfill'])->middleware('permission:inventory.stock.adjust');

            Route::get('/raw-materials', [RawMaterialController::class, 'index']);
            Route::post('/raw-materials', [RawMaterialController::class, 'store']);
            Route::put('/raw-materials/{id}', [RawMaterialController::class, 'update']);
            Route::delete('/raw-materials/{id}', [RawMaterialController::class, 'destroy']);
            Route::put('/raw-materials/{id}/toggle-request', [RawMaterialController::class, 'toggleShoppingRequest']);
            Route::post('/raw-materials/{id}/restock', [RawMaterialController::class, 'restock']);
            Route::post('/raw-materials/import', [ImportController::class, 'importRawMaterials']);
            Route::get('/raw-materials/import/template', [ImportController::class, 'downloadTemplate']);
            Route::get('/accounting/accounts', [AccountingController::class, 'accounts']);
            Route::post('/accounting/accounts', [AccountingController::class, 'addAccount']);
            Route::get('/accounting/journals', [AccountingController::class, 'journals']);
            Route::post('/accounting/journals', [AccountingController::class, 'addJournal']);
            Route::get('/finance/dashboard', [FinanceController::class, 'dashboard']);
            Route::post('/finance/expenses', [FinanceController::class, 'addExpense']);
            Route::get('/finance/export', [FinanceController::class, 'exportCsv']);
            Route::get('/customers', [CustomerController::class, 'index']);
            Route::post('/customers', [CustomerController::class, 'store']);
            Route::post('/customers/search', [CustomerController::class, 'search']);
            Route::get('/employees', [EmployeeController::class, 'index']);
            Route::get('/employees/search', [EmployeeController::class, 'search']);
            Route::get('/employees/{id}', [EmployeeController::class, 'show']);
            Route::post('/employees', [EmployeeController::class, 'store']);
            Route::put('/employees/{id}', [EmployeeController::class, 'update']);
            Route::delete('/employees/{id}', [EmployeeController::class, 'destroy']);
            Route::get('/leaves', [LeaveController::class, 'index']);
            Route::post('/leaves', [LeaveController::class, 'store']);
            Route::get('/leaves/{leave}', [LeaveController::class, 'show']);
            Route::post('/leaves/{leave}/approve', [LeaveController::class, 'approve']);
            Route::post('/leaves/{leave}/reject', [LeaveController::class, 'reject']);
            Route::delete('/leaves/{leave}', [LeaveController::class, 'destroy']);
            Route::get('/leaves/attendance/report', [LeaveController::class, 'attendanceReport']);
            Route::get('/hrm/summary', [HrmController::class, 'summary']);
            Route::get('/hrm/attendances', [HrmController::class, 'attendances']);
            Route::post('/hrm/clock-in', [HrmController::class, 'clockIn']);
            Route::get('/hrm/payrolls', [HrmController::class, 'payrolls']);
            Route::post('/hrm/payrolls', [HrmController::class, 'generatePayroll']);
            Route::put('/hrm/payrolls/{id}/pay', [HrmController::class, 'paySalary']);
        });
    });
});

Route::get('/user', function (Request $request) { return $request->user(); })->middleware(['auth:sanctum', 'request.id']);
