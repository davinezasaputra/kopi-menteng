<?php
namespace App\Observers;

use App\Models\Order;
use App\Models\JournalEntry;
use App\Models\Account;

class SaleObserver
{
    public function created(Order $order): void
    {
        if ($order->status === 'paid') {
            $this->recordToJournal($order);
        }
    }

    public function updated(Order $order): void
    {
        if ($order->isDirty('status') && $order->status === 'paid') {
            $this->recordToJournal($order);
        }
    }

    private function recordToJournal(Order $order): void
    {
        $totalAmount = $order->total ?? 0;
        if ($totalAmount <= 0) return;

        // 1. Cari Akun Kas (Biasanya tipe 'asset' atau kode '101') untuk posisi DEBIT (Uang Masuk)
        $cashAccount = Account::where('code', '101')->first() 
                    ?? Account::where('type', 'asset')->first();

        // 2. Cari Akun Pendapatan (Biasanya tipe 'revenue' atau kode '401') untuk posisi KREDIT
        $revenueAccount = Account::where('type', 'revenue')->first() 
                       ?? Account::first();

        // Catat Sisi Debit (Kas Bertambah) jika akun kas ditemukan
        if ($cashAccount) {
            JournalEntry::create([
                'account_id'  => $cashAccount->id,
                'date'        => $order->updated_at->toDateString(),
                'description' => 'Penerimaan Kas POS - Nota: ' . $order->invoice_number,
                'debit'       => $totalAmount,
                'credit'      => 0,
            ]);
        }

        // Catat Sisi Kredit (Pendapatan Bertambah) jika akun pendapatan ditemukan
        if ($revenueAccount) {
            JournalEntry::create([
                'account_id'  => $revenueAccount->id,
                'date'        => $order->updated_at->toDateString(),
                'description' => 'Pendapatan Penjualan - Nota: ' . $order->invoice_number,
                'debit'       => 0,
                'credit'      => $totalAmount,
            ]);
        }
    }
}