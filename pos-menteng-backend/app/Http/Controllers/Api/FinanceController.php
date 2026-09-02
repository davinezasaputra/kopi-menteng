<?php

namespace App\Http\Controllers\Api;

use App\Domain\Accounting\Services\OperationalExpenseAccountingService;
use App\Domain\Audit\Services\AuditService;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OperationalExpense;
use App\Models\RestockHistory;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Support\Tenancy\TenantContext;

class FinanceController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditService $audit,
        private readonly OperationalExpenseAccountingService $expenseAccounting,
    ) {}

    private function scopedOrders()
    {
        return Order::query()
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->companyId())
            ->where('branch_id', $this->context->branchId());
    }

    private function scopedExpenses()
    {
        return OperationalExpense::query()
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->companyId())
            ->where('branch_id', $this->context->branchId());
    }

    private function scopedRestocks()
    {
        return RestockHistory::query()
            ->where('restock_histories.tenant_id', $this->context->tenantId())
            ->where('restock_histories.company_id', $this->context->companyId())
            ->where('restock_histories.branch_id', $this->context->branchId());
    }

    public function dashboard(Request $request)
    {
        $period = $request->input('period', 'monthly');
        $month = (int) $request->input('month', Carbon::now()->month);
        $year = (int) $request->input('year', Carbon::now()->year);
        $date = Carbon::parse($request->input('date', Carbon::now()->toDateString()));

        $applyPeriod = function ($query, string $column) use ($period, $month, $year, $date) {
            if ($period === 'daily') return $query->whereDate($column, $date->toDateString());
            if ($period === 'yearly') return $query->whereYear($column, $year);
            return $query->whereMonth($column, $month)->whereYear($column, $year);
        };

        $orders = $applyPeriod($this->scopedOrders(), 'created_at')->whereIn('status', ['paid', 'settlement'])->get();
        $totalGross = $orders->sum('total');
        $totalDpp = $orders->sum('subtotal');
        $totalTax = $orders->sum('tax');
        $totalCogs = $orders->sum('total_cogs');

        $totalOpex = $applyPeriod($this->scopedExpenses(), 'expense_date')->sum('amount');
        $expenses = $applyPeriod($this->scopedExpenses(), 'expense_date')->orderByDesc('expense_date')->orderByDesc('id')->get(['id','name','amount','expense_date','recorded_by']);

        $restockPurchases = $applyPeriod($this->scopedRestocks()->join('raw_materials', 'raw_materials.id', '=', 'restock_histories.raw_material_id'), 'restock_histories.created_at')
            ->orderByDesc('restock_histories.created_at')
            ->get(['restock_histories.id','restock_histories.quantity_added','restock_histories.total_cost','restock_histories.restocked_by','restock_histories.created_at','raw_materials.name as material_name','raw_materials.category','raw_materials.unit']);

        $shoppingSummary = ['bar' => $restockPurchases->where('category','bar')->sum('total_cost'), 'dapur' => $restockPurchases->where('category','dapur')->sum('total_cost')];
        $netProfit = $totalDpp - $totalCogs - $totalOpex;

        $chartData = $applyPeriod($this->scopedOrders()->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(total) as gross_revenue'), DB::raw('SUM(net_profit) as net_profit')), 'created_at')
            ->whereIn('status', ['paid','settlement'])->groupBy('date')->orderBy('date')->get();

        $topProducts = OrderItem::select('product_id', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(subtotal) as total_revenue'))
            ->with('product:id,name,price')
            ->whereHas('order', function ($q) use ($applyPeriod) {
                $q->where('tenant_id', $this->context->tenantId())
                  ->where('company_id', $this->context->companyId())
                  ->where('branch_id', $this->context->branchId());
                $applyPeriod($q, 'created_at')->whereIn('status', ['paid','settlement']);
            })->groupBy('product_id')->orderByDesc('total_qty')->take(5)->get();

        return response()->json(['status'=>'success','data'=>[
            'summary'=>['gross_revenue'=>$totalGross,'tax_payable'=>$totalTax,'total_cogs'=>$totalCogs,'total_opex'=>$totalOpex,'net_profit'=>$netProfit,'total_orders'=>$orders->count()],
            'chart_data'=>$chartData,'top_products'=>$topProducts,'expenses'=>$expenses,'restock_purchases'=>$restockPurchases,'shopping_summary'=>$shoppingSummary,
        ]]);
    }

    public function addExpense(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
        ]);

        $expense = DB::transaction(function () use ($validated, $request) {
            $expense = OperationalExpense::create($validated + [
                'tenant_id' => $this->context->tenantId(),
                'company_id' => $this->context->companyId(),
                'branch_id' => $this->context->branchId(),
                'recorded_by' => $request->user()?->name,
            ]);

            $this->expenseAccounting->postExpense($expense->fresh());
            return $expense->fresh();
        });

        $this->audit->record('created', 'finance.operational_expense', $expense, null, $expense->toArray());

        return response()->json([
            'status' => 'success',
            'message' => 'Biaya operasional dicatat dan dijurnal ke ERP.',
            'data' => $expense,
        ], 201);
    }

    public function exportCsv(Request $request)
    {
        $period = $request->input('period','monthly');
        $month = (int) $request->input('month',Carbon::now()->month);
        $year = (int) $request->input('year',Carbon::now()->year);
        $date = Carbon::parse($request->input('date',Carbon::now()->toDateString()));
        $orders = $this->scopedOrders()
            ->when($period==='daily',fn($q)=>$q->whereDate('created_at',$date->toDateString()))
            ->when($period==='monthly',fn($q)=>$q->whereMonth('created_at',$month)->whereYear('created_at',$year))
            ->when($period==='yearly',fn($q)=>$q->whereYear('created_at',$year))
            ->whereIn('status',['paid','settlement'])->orderBy('created_at')->get();

        $csvData="Tanggal,No. Invoice,Tipe Pesanan,Pelanggan,Total Bayar (Rp),Modal HPP (Rp),Laba Bersih (Rp)\n";
        foreach($orders as $order){
            $rowDate=$order->created_at->format('Y-m-d H:i');
            $customer=str_replace('"','""',$order->customer_name ?? '-');
            $csvData .= "{$rowDate},{$order->invoice_number},{$order->order_type},\"{$customer}\",{$order->total},{$order->total_cogs},{$order->net_profit}\n";
        }
        $filenamePeriod=$period==='daily'?$date->format('Y-m-d'):($period==='yearly'?(string)$year:"{$year}-{$month}");
        return response($csvData)->header('Content-Type','text/csv')->header('Content-Disposition',"attachment; filename=Rekap_Pendapatan_{$filenamePeriod}.csv");
    }
}
