<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Http\Request;

class AccountingController extends Controller
{
    // Mengambil Daftar Akun + Kalkulasi Saldo Terkini
    public function accounts()
    {
        $accounts = Account::withSum('journalEntries as total_debit', 'debit')
                           ->withSum('journalEntries as total_credit', 'credit')
                           ->orderBy('code', 'asc')->get()->map(function($acc) {
                               $debit = $acc->total_debit ?? 0;
                               $credit = $acc->total_credit ?? 0;
                               // Saldo Normal: Aset & Beban (Debit - Kredit), Kewajiban/Ekuitas/Pendapatan (Kredit - Debit)
                               $acc->balance = in_array($acc->type, ['asset', 'expense']) ? $debit - $credit : $credit - $debit;
                               return $acc;
                           });
        return response()->json(['status' => 'success', 'data' => $accounts]);
    }

    public function addAccount(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:accounts,code',
            'name' => 'required|string|max:255',
            'type' => 'required|in:asset,liability,equity,revenue,expense'
        ]);
        Account::create($validated);
        return response()->json(['status' => 'success', 'message' => 'Kode Akun berhasil dibuat.']);
    }

    // Mengambil Riwayat Jurnal Umum
    public function journals()
    {
        $journals = JournalEntry::with('account:id,code,name')
                                ->orderBy('date', 'desc')->orderBy('id', 'desc')->get();
        return response()->json(['status' => 'success', 'data' => $journals]);
    }

    public function addJournal(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'date' => 'required|date',
            'description' => 'required|string',
            'debit' => 'nullable|numeric|min:0',
            'credit' => 'nullable|numeric|min:0',
        ]);
        
        $validated['debit'] = $validated['debit'] ?? 0;
        $validated['credit'] = $validated['credit'] ?? 0;

        JournalEntry::create($validated);
        return response()->json(['status' => 'success', 'message' => 'Jurnal berhasil dicatat.']);
    }
}