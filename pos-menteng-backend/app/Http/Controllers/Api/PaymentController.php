<?php

namespace App\Http\Controllers\Api;

use App\Domain\Accounting\Services\PosOrderAccountingService;
use App\Domain\Identity\Models\Membership;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly PosOrderAccountingService $accounting,
    ) {}

    public function handleWebhook(Request $request)
    {
        $transactionStatus = $request->input('transaction_status');
        $paymentType = $request->input('payment_type');
        $invoiceNumber = $request->input('order_id');
        $statusCode = $request->input('status_code');

        Log::info("Midtrans Webhook Masuk: Invoice {$invoiceNumber} - Status: {$transactionStatus} - Code: {$statusCode}");

        $order = Order::query()->where('invoice_number', $invoiceNumber)->first();
        if (! $order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Nomor Invoice tidak ditemukan di sistem POS',
            ], 404);
        }

        if (in_array($transactionStatus, ['settlement', 'capture'], true)) {
            $membership = Membership::query()
                ->where('tenant_id', $order->tenant_id)
                ->where('company_id', $order->company_id)
                ->where('branch_id', $order->branch_id)
                ->where('status', 'active')
                ->orderByDesc('is_primary')
                ->first();

            if (! $membership) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Active ERP membership for the payment organization was not found.',
                ], 422);
            }

            $this->context->setMembership($membership);

            try {
                DB::transaction(function () use ($order, $paymentType) {
                    $order->update([
                        'status' => 'paid',
                        'payment_method' => $paymentType,
                    ]);

                    $this->accounting->postPaidOrder($order->fresh());
                });
            } catch (\Throwable $e) {
                Log::error('POS payment webhook accounting failed.', [
                    'invoice_number' => $invoiceNumber,
                    'order_id' => $order->id,
                    'message' => $e->getMessage(),
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Pembayaran belum dapat diposting karena jurnal ERP gagal diproses.',
                ], 422);
            }
        } elseif ($transactionStatus === 'pending') {
            $order->update([
                'status' => 'pending',
            ]);
        } elseif (in_array($transactionStatus, ['deny', 'expire', 'cancel'], true)) {
            DB::transaction(function () use ($order) {
                $order->update([
                    'status' => 'canceled',
                ]);

                $order->load('items');
                foreach ($order->items as $item) {
                    $item->product()->increment('stock', $item->quantity);
                }
            });
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Webhook berhasil diproses',
        ], 200);
    }
}
