<?php
namespace App\Observers;

use App\Models\Sale;
use App\Models\GeneralLedger;

class SaleObserver
{
    public function created(Sale $sale): void
    {
        // 1. Jurnal Pendapatan: Bertambahnya Kas/Bank dari Penjualan
        GeneralLedger::create([
            'transaction_date' => $sale->created_at->toDateString(),
            'description'      => 'Penjualan POS - No. Nota: ' . $sale->invoice_number,
            'account_category' => 'Pendapatan Penjualan',
            'debit'            => $sale->grand_total, // Uang Masuk
            'credit'           => 0,
            'reference_type'   => Sale::class,
            'reference_id'     => $sale->id,
        ]);

        // 2. Jurnal HPP: Pencatatan Beban Pokok atas Bahan Baku Terpakai
        if ($sale->total_cost_of_goods_sold > 0) {
            GeneralLedger::create([
                'transaction_date' => $sale->created_at->toDateString(),
                'description'      => 'HPP Barang Terjual - No. Nota: ' . $sale->invoice_number,
                'account_category' => 'Harga Pokok Penjualan (HPP)',
                'debit'            => $sale->total_cost_of_goods_sold, // Beban Bertambah
                'credit'           => 0,
                'reference_type'   => Sale::class,
                'reference_id'     => $sale->id,
            ]);
        }
    }
}

