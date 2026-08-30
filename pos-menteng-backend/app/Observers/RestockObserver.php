<?php

namespace App\Observers;

use App\Models\RestockHistory;
use App\Models\JournalEntry;
use App\Models\Account;

class RestockObserver
{
    public function created(RestockHistory $restock): void
    {
        $this->recordToJournal($restock);
    }

    private function recordToJournal(RestockHistory $restock): void
    {
        $totalAmount = $restock->total_cost ?? 0;
        if ($totalAmount <= 0) return;

        // 1. Cari Akun Persediaan Bahan / Beban Pembelian (Cth: Kode '104' atau '530' atau tipe 'asset'/'expense')
        // Anda bisa sesuaikan kodenya dengan akun khusus bahan dapur/bar di tabel accounts Anda
        $inventoryAccount = Account::where('code', '530')->first() 
                         ?? Account::where('type', 'expense')->first();

        // 2. Cari Akun Kas (Tipe 'asset' atau kode '101') untuk posisi Kredit (Kas Keluar)
        $cashAccount = Account::where('code', '101')->first() 
                    ?? Account::where('type', 'asset')->first();

        // Catat Sisi Debit (Persediaan/Pembelian Bahan Bertambah)
        if ($inventoryAccount) {
            JournalEntry::create([
                'account_id'             => $inventoryAccount->id,
                'date'                   => $restock->created_at ? $restock->created_at->toDateString() : now()->toDateString(),
                'description'            => 'Pembelian Bahan Dapur/Bar - ID Restock: ' . $restock->id,
                'debit'                  => $totalAmount,
                'credit'                 => 0,
                'restock_history_id'     => $restock->id, // Jika kolom relasi sudah ditambahkan di migrasi journal_entries
            ]);
        }

        // Catat Sisi Kredit (Kas Berkurang untuk Membeli Bahan)
        if ($cashAccount) {
            JournalEntry::create([
                'account_id'             => $cashAccount->id,
                'date'                   => $restock->created_at ? $restock->created_at->toDateString() : now()->toDateString(),
                'description'            => 'Kas Keluar untuk Restock Bahan - Oleh: ' . $restock->restocked_by,
                'debit'                  => 0,
                'credit'                 => $totalAmount,
                'restock_history_id'     => $restock->id,
            ]);
        }
    }
}