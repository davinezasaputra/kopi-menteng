<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OperationalExpense;
use App\Models\RestockHistory;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    public function dashboard(Request $request)
    {
        $period = $request->input('period', 'monthly');
        $month = $request->input('month', Carbon::now()->month);
        $year = $request->input('year', Carbon::now()->year);
        $date = Carbon::parse($request->input('date', Carbon::now()->toDateString()));

        $applyPeriod = function ($query, string $column) use ($period, $month, $year, $date) {
            if ($period === 'daily') {
                return $query->whereDate($column, $date->toDateString());
            }

            if ($period === 'yearly') {
                return $query->whereYear($column, $year);
            }

            return $query->whereMonth($column, $month)->whereYear($column, $year);
        };

        $orders = $applyPeriod(Order::query(), 'created_at')
            ->whereIn('status', ['paid', 'settlement'])
            ->get();

        $totalGross = $orders->sum('total');
        $totalDpp = $orders->sum('subtotal');
        $totalTax = $orders->sum('tax');
        $totalCogs = $orders->sum('total_cogs');
        
        // AMBIL BIAYA OPERASIONAL BULAN INI
        $totalOpex = $applyPeriod(OperationalExpense::query(), 'expense_date')->sum('amount');

        $expenses = $applyPeriod(OperationalExpense::query(), 'expense_date')
                          ->orderByDesc('expense_date')
                          ->orderByDesc('id')
                          ->get(['id', 'name', 'amount', 'expense_date', 'recorded_by']);

        $restockPurchases = RestockHistory::query()
            ->join('raw_materials', 'raw_materials.id', '=', 'restock_histories.raw_material_id')
            ->when($period === 'daily', fn ($query) => $query->whereDate('restock_histories.created_at', $date->toDateString()))
            ->when($period === 'monthly', fn ($query) => $query->whereMonth('restock_histories.created_at', $month)->whereYear('restock_histories.created_at', $year))
            ->when($period === 'yearly', fn ($query) => $query->whereYear('restock_histories.created_at', $year))
            ->orderByDesc('restock_histories.created_at')
            ->get([
                'restock_histories.id',
                'restock_histories.quantity_added',
                'restock_histories.total_cost',
                'restock_histories.restocked_by',
                'restock_histories.created_at',
                'raw_materials.name as material_name',
                'raw_materials.category',
                'raw_materials.unit',
            ]);

        $shoppingSummary = [
            'bar' => $restockPurchases->where('category', 'bar')->sum('total_cost'),
            'dapur' => $restockPurchases->where('category', 'dapur')->sum('total_cost'),
        ];

        // LABA BERSIH REAL = Pendapatan Murni - Modal Bahan - Biaya Operasional
        $netProfit = $totalDpp - $totalCogs - $totalOpex;

        $chartData = Order::select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(total) as gross_revenue'), DB::raw('SUM(net_profit) as net_profit'))
            ->when($period === 'daily', fn ($query) => $query->whereDate('created_at', $date->toDateString()))
            ->when($period === 'monthly', fn ($query) => $query->whereMonth('created_at', $month)->whereYear('created_at', $year))
            ->when($period === 'yearly', fn ($query) => $query->whereYear('created_at', $year))
            ->whereIn('status', ['paid', 'settlement'])
            ->groupBy('date')->orderBy('date', 'asc')->get();

        $topProducts = OrderItem::select('product_id', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(subtotal) as total_revenue'))
            ->with('product:id,name,price')
            ->whereHas('order', function($q) use ($applyPeriod) {
                $applyPeriod($q, 'created_at')->whereIn('status', ['paid', 'settlement']);
            })->groupBy('product_id')->orderBy('total_qty', 'desc')->take(5)->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'summary' => [
                    'gross_revenue' => $totalGross,
                    'tax_payable' => $totalTax,
                    'total_cogs' => $totalCogs,
                    'total_opex' => $totalOpex,
                    'net_profit' => $netProfit,
                    'total_orders' => $orders->count()
                ],
                'chart_data' => $chartData,
                'top_products' => $topProducts,
                'expenses' => $expenses,
                'restock_purchases' => $restockPurchases,
                'shopping_summary' => $shoppingSummary,
            ]
        ]);
    }

    public function addExpense(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'expense_date' => 'required|date'
        ]);
        
        $validated['recorded_by'] = $request->user()->name;
        OperationalExpense::create($validated);
        
        return response()->json(['status' => 'success', 'message' => 'Biaya operasional dicatat.']);
    }

    public function exportCsv(Request $request)
    {
        $period = $request->input('period', 'monthly');
        $month = $request->input('month', Carbon::now()->month);
        $year = $request->input('year', Carbon::now()->year);
        $date = Carbon::parse($request->input('date', Carbon::now()->toDateString()));

        $orders = Order::query()
                       ->when($period === 'daily', fn ($query) => $query->whereDate('created_at', $date->toDateString()))
                       ->when($period === 'monthly', fn ($query) => $query->whereMonth('created_at', $month)->whereYear('created_at', $year))
                       ->when($period === 'yearly', fn ($query) => $query->whereYear('created_at', $year))
                       ->whereIn('status', ['paid', 'settlement'])
                       ->orderBy('created_at', 'asc')->get();

        // Header CSV format Excel Indonesia
        $csvData = "Tanggal,No. Invoice,Tipe Pesanan,Pelanggan,Total Bayar (Rp),Modal HPP (Rp),Laba Bersih (Rp)\n";
        
        foreach($orders as $order) {
            $date = $order->created_at->format('Y-m-d H:i');
            $customer = $order->customer_name ?? '-';
            $csvData .= "{$date},{$order->invoice_number},{$order->order_type},\"{$customer}\",{$order->total},{$order->total_cogs},{$order->net_profit}\n";
        }

        $filenamePeriod = $period === 'daily' ? $date->format('Y-m-d') : ($period === 'yearly' ? (string) $year : "{$year}-{$month}");

        return response($csvData)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=Rekap_Pendapatan_{$filenamePeriod}.csv");
    }
}