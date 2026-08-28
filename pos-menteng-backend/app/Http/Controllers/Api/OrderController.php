<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Midtrans\Config;
use Midtrans\Snap;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $today = Carbon::today();
        $orders = Order::with('items.product')->whereDate('created_at', $today)->orderBy('created_at', 'desc')->get();
        return response()->json([
            'status' => 'success',
            'data' => $orders
        ]);
    }

    public function checkout(Request $request)
    {
        $user = $request->user();

        $activeShift = Shift::where('user_id', $user->id)->where('status', 'open')->first();
        if (!$activeShift) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda harus membuka shift terlebih dahulu.'
            ], 403);
        }

        $validated = $request->validate([
            'payment_method' => 'required|in:cash,qris,bank_transfer,unpaid',
            'order_type' => 'required|in:dine_in,takeaway',
            'customer_id' => 'nullable|exists:customers,id',
            'customer_name' => 'nullable|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            $subtotal = 0; // Total dari harga menu (sudah termasuk PPN)
            $totalCogs = 0; // Modal (HPP)
            $processedItems = [];

            foreach ($validated['items'] as $item) {
                $product = Product::lockForUpdate()->with('rawMaterials')->findOrFail($item['product_id']);

                if ($product->stock < $item['quantity']) {
                    throw new \Exception("Stok {$product->name} tidak cukup. Sisa: {$product->stock}");
                }

                $itemSubtotal = $product->price * $item['quantity'];
                $subtotal += $itemSubtotal;
                $itemCogs = 0; // Modal per item pesanan

                $product->decrement('stock', $item['quantity']);
                
                foreach ($product->rawMaterials as $material) {
                    $totalMaterialNeeded = $material->pivot->quantity_needed * $item['quantity'];
                    
                    if ($material->stock < $totalMaterialNeeded) {
                        throw new \Exception("Bahan baku '{$material->name}' habis!");
                    }
                    
                    $material->decrement('stock', $totalMaterialNeeded);

                    if ($material->fresh()->stock <= $material->min_stock_level) {
                        $material->update(['is_requested' => true]);
                    }
                    
                    // Kalkulasi HPP: Kebutuhan bahan x Harga Rata-rata Satuan
                    $itemCogs += ($totalMaterialNeeded * $material->price_per_unit);
                }

                $totalCogs += $itemCogs; // Akumulasi modal ke total keranjang

                $processedItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                    'subtotal' => $itemSubtotal,
                ];
            }

            // 1. Dapatkan Total Harga Mentah dari Keranjang
            $rawTotal = $subtotal; 

            // 2. LOGIKA CRM MEMBER (Diskon)
            $discount = 0;
            $customer = null;
            if (!empty($validated['customer_id'])) {
                $customer = Customer::find($validated['customer_id']);
                if ($customer) {
                    if ($customer->tier === 'vip') $discount = $rawTotal * 0.10; // VIP Diskon 10%
                    elseif ($customer->tier === 'gold') $discount = $rawTotal * 0.05; // Gold Diskon 5%
                }
            }

            // 3. LOGIKA PAJAK INKLUSIF & LABA BERSIH
            $total = $rawTotal - $discount; // Harga akhir setelah diskon
            $basePrice = $total / 1.11; // DPP (Dasar Pengenaan Pajak) baru
            $tax = $total - $basePrice; // Potongan PPN baru
            $netProfit = $basePrice - $totalCogs; // Laba Bersih = DPP - Modal

            $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(Str::random(5));

            $order = Order::create([
                'user_id' => $user->id,
                'shift_id' => $activeShift->id,
                'customer_id' => $customer ? $customer->id : null, // Simpan Relasi Member
                'invoice_number' => $invoiceNumber,
                'subtotal' => $basePrice,
                'tax' => $tax,
                'discount' => $discount, // Rekam Potongan Harga
                'total' => $total,
                'total_cogs' => $totalCogs,
                'net_profit' => $netProfit,
                'payment_method' => $validated['payment_method'],
                'order_type' => $validated['order_type'],
                'customer_name' => $validated['customer_name'],
                'status' => $validated['payment_method'] === 'cash' ? 'paid' : 'pending',
            ]);

            // 4. BERIKAN POIN LOYALITAS (1 Poin tiap Rp 10.000)
            if ($customer && $validated['payment_method'] === 'cash') {
                $earnedPoints = floor($total / 10000);
                $customer->increment('points', $earnedPoints);
            }

            foreach ($processedItems as $processedItem) {
                $order->items()->create($processedItem);
            }

            $paymentUrl = null; 

            if ($validated['payment_method'] === 'cash') {
                $activeShift->increment('expected_ending_cash', $total);
            } else {
                Config::$serverKey = config('midtrans.server_key');
                Config::$isProduction = config('midtrans.is_production');
                Config::$isSanitized = true;
                Config::$is3ds = true;

                $midtransParams = [
                    'transaction_details' => [
                        'order_id' => $order->invoice_number,
                        'gross_amount' => (int) $order->total,
                    ],
                    'customer_details' => [
                        'first_name' => 'Pelanggan',
                        'last_name' => 'Cafe Menteng',
                    ],
                ];

                $snapToken = Snap::getSnapToken($midtransParams);
                $paymentUrl = "https://app.sandbox.midtrans.com/snap/v3/redirection/" . $snapToken;
            }

            DB::commit();
            $order->load('items.product');

            return response()->json([
                'status' => 'success',
                'message' => 'Transaksi berhasil diproses',
                'payment_url' => $paymentUrl,
                'data' => $order
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Transaksi gagal: ' . $e->getMessage()
            ], 500);
        }
    }

    public function history(Request $request)
    {
        $orders = Order::with(['items.product'])->orderBy('created_at', 'desc')->get();
        return response()->json([
            'status' => 'success',
            'data' => $orders
        ]);
    }
}