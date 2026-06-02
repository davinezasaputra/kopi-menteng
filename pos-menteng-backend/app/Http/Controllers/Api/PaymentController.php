<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function handleWebhook(Request $request)
    {
        $transactionStatus = $request->input('transaction_status');
        $paymentType = $request->input('payment_type');
        $invoiceNumber = $request->input('order_id');
        $statusCode = $request->input('status_code');
        Log::info("Midtrans Webhook Masuk: Invoice {$invoiceNumber} - Status: {$transactionStatus}");
        $order = Order::where('invoice_number', $invoiceNumber)->first();

        if (!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Nomor Invoice tidak ditemukan di sistem POS'
            ], 404);
        }
        if ($transactionStatus == 'settlement' || $transactionStatus == 'capture') {
            $order->update([
                'status' => 'paid',
                'payment_method' => $paymentType
            ]);

        } elseif ($transactionStatus == 'pending') {
            
            $order->update([
                'status' => 'pending'
            ]);

        } elseif (in_array($transactionStatus, ['deny', 'expire', 'cancel'])) {
            $order->update([
                'status' => 'canceled'
            ]);
            $order->load('items');
            foreach ($order->items as $item) {
                $item->product()->increment('stock', $item->quantity);
            }
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Webhook berhasil diproses'
        ], 200);
    }
}