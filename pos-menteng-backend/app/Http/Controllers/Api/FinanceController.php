<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    public function dashboard(Request $request)
    {
        // 1. Filter Waktu (Bulan & Tahun Saat Ini)
        $month = $request->input('month', Carbon::now()->month);
        $year = $request->input('year', Carbon::now()->year);

        // 2. Tarik semua pesanan LUNAS di bulan tersebut
        $orders = Order::whereMonth('created_at', $month)
                       ->whereYear('created_at', $year)
                       ->whereIn('status', ['paid', 'settlement']) // Antisipasi status QRIS
                       ->get();

        // 3. Kalkulasi Matriks Finansial
        $totalGross = $orders->sum('total');      // Omzet Kotor (Termasuk Pajak)
        $totalDpp = $orders->sum('subtotal');     // Pendapatan Murni (Tanpa Pajak)
        $totalTax = $orders->sum('tax');          // PPN Titipan Negara
        $totalCogs = $orders->sum('total_cogs');  // HPP / Modal Bahan Baku
        $netProfit = $orders->sum('net_profit');  // Laba Bersih (DPP - HPP)

        // 4. Data untuk Grafik Garis (Dikelompokkan per Tanggal)
        $chartData = Order::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('SUM(total) as gross_revenue'),
            DB::raw('SUM(net_profit) as net_profit')
        )
        ->whereMonth('created_at', $month)
        ->whereYear('created_at', $year)
        ->whereIn('status', ['paid', 'settlement'])
        ->groupBy('date')
        ->orderBy('date', 'asc')
        ->get();

        // 5. Matriks Menu Terlaris & Paling Menguntungkan
        $topProducts = OrderItem::select('product_id', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(subtotal) as total_revenue'))
            ->with('product:id,name,price')
            ->whereHas('order', function($q) use ($month, $year) {
                $q->whereMonth('created_at', $month)->whereYear('created_at', $year)->whereIn('status', ['paid', 'settlement']);
            })
            ->groupBy('product_id')
            ->orderBy('total_qty', 'desc')
            ->take(5)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'summary' => [
                    'gross_revenue' => $totalGross,
                    'tax_payable' => $totalTax,
                    'total_cogs' => $totalCogs,
                    'net_profit' => $netProfit,
                    'total_orders' => $orders->count()
                ],
                'chart_data' => $chartData,
                'top_products' => $topProducts
            ]
        ]);
    }
}