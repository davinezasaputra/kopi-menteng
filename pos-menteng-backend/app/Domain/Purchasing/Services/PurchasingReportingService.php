<?php

namespace App\Domain\Purchasing\Services;

use App\Domain\Purchasing\Models\GoodsReceipt;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Purchasing\Models\SupplierInvoice;
use App\Domain\Purchasing\Models\SupplierPayment;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;

class PurchasingReportingService
{
    public function __construct(private readonly TenantContext $context)
    {
    }

    private function scope($query)
    {
        return $query
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->companyId())
            ->where('branch_id', $this->context->branchId());
    }

    public function dashboard(?string $from = null, ?string $to = null): array
    {
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : null;
        $toDate = $to ? Carbon::parse($to)->endOfDay() : null;

        $po = $this->scope(PurchaseOrder::query())
            ->when($fromDate, fn ($q) => $q->where('created_at', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->where('created_at', '<=', $toDate));

        $gr = $this->scope(GoodsReceipt::query())
            ->when($fromDate, fn ($q) => $q->where('created_at', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->where('created_at', '<=', $toDate));

        $invoices = $this->scope(SupplierInvoice::query());
        $payments = $this->scope(SupplierPayment::query());

        $purchaseValue = (float) $po->sum('grand_total');
        $receiptValue = (float) $gr->sum(
            DB::raw('(select coalesce(sum(gri.line_value),0) from goods_receipt_items gri where gri.goods_receipt_id = goods_receipts.id)')
        );
        $invoiceValue = (float) $invoices->sum('total_amount');
        $paidValue = (float) $invoices->sum('paid_amount');

        return [
            'period' => ['from' => $from, 'to' => $to],
            'purchase_orders' => [
                'total' => (clone $po)->count(),
                'draft' => (clone $po)->where('status','draft')->count(),
                'submitted' => (clone $po)->where('status','submitted')->count(),
                'approved' => (clone $po)->where('status','approved')->count(),
                'partially_received' => (clone $po)->where('status','partially_received')->count(),
                'received' => (clone $po)->where('status','received')->count(),
                'cancelled' => (clone $po)->where('status','cancelled')->count(),
                'purchase_value' => round($purchaseValue,2),
            ],
            'goods_receipts' => [
                'count' => $gr->count(),
                'received_value' => round($receiptValue,2),
            ],
            'accounts_payable' => [
                'invoice_count' => $invoices->count(),
                'invoice_value' => round($invoiceValue,2),
                'paid_value' => round($paidValue,2),
                'outstanding' => round(max(0,$invoiceValue - $paidValue),2),
                'overdue_count' => (clone $invoices)
                    ->whereNotIn('status',['paid'])
                    ->whereNotNull('due_date')
                    ->whereDate('due_date','<',now()->toDateString())
                    ->count(),
            ],
            'supplier_payments' => [
                'count' => $payments->count(),
                'value' => round((float)$payments->sum('amount'),2),
            ],
        ];
    }

    public function apAging(): array
    {
        $invoices = $this->scope(SupplierInvoice::query())
            ->with('supplier')
            ->where('status','!=','paid')
            ->where('total_amount','>',0)
            ->get();

        $buckets = [
            'current' => 0.0,
            '1_30' => 0.0,
            '31_60' => 0.0,
            '61_90' => 0.0,
            '91_plus' => 0.0,
        ];

        foreach ($invoices as $invoice) {
            $outstanding = max(0, (float)$invoice->total_amount - (float)$invoice->paid_amount);
            if ($outstanding <= 0) continue;

            if (! $invoice->due_date || $invoice->due_date->isFuture()) {
                $buckets['current'] += $outstanding;
                continue;
            }

            $days = $invoice->due_date->diffInDays(now());
            $bucket = match (true) {
                $days <= 30 => '1_30',
                $days <= 60 => '31_60',
                $days <= 90 => '61_90',
                default => '91_plus',
            };
            $buckets[$bucket] += $outstanding;
        }

        return array_map(fn ($value) => round($value,2), $buckets) + [
            'total_outstanding' => round(array_sum($buckets),2),
        ];
    }

    public function supplierPerformance(): array
    {
        $suppliers = $this->scope(Supplier::query())
            ->withCount(['?']);
        return [];
    }
}
