<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shift;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Midtrans\Config;
use Midtrans\Snap;

class OrderController extends Controller
{
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
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            $subtotal = 0;
            $processedItems = [];

            foreach ($validated['items'] as $item) {
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);

                if ($product->stock < $item['quantity']) {
                    throw new \Exception("Stok {$product->name} tidak cukup. Sisa: {$product->stock}");
                }

                $itemSubtotal = $product->price * $item['quantity'];
                $subtotal += $itemSubtotal;

                $processedItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                    'subtotal' => $itemSubtotal,
                ];

                $product->decrement('stock', $item['quantity']);
            }

            $tax = $subtotal * 0.11;
            $total = $subtotal + $tax;
            $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(Str::random(5));

            $order = Order::create([
                'user_id' => $user->id,
                'shift_id' => $activeShift->id,
                'invoice_number' => $invoiceNumber,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'discount' => 0, 
                'total' => $total,
                'payment_method' => $validated['payment_method'],
                'status' => $validated['payment_method'] === 'cash' ? 'paid' : 'pending',
            ]);

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
    public function history(Request $request){
        $orders = Order::with(['items.product',])->orderBy('created_at', 'desc')->get();
        return response()->json([
            'status' => 'success',
            'data' => $orders
        ]);
    }
}