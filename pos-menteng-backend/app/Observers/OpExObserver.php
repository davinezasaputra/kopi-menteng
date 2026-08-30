<?php

namespace App\Observers;


use App\Models\JournalEntry;
use App\Models\OperationalExpense;
use App\Models\Account;

class OpExObserver
{
    public function created(OperationalExpense $expense): void
    {
        $this->recordToJournal($expense);
    }
    private function recordToJournal(OperationalExpense $expense){
        $totalAmount = $expense->amount ?? 0;
        if ($totalAmount<=0){
            return;}

            $expenseAccount = Account::where('code', '512')->first() ?? Account::where('type', 'expense')->first();
            $cashAccount = Account::where('code', '101')->first() ?? Account::where('type', 'asset');

            $expenseDate = $expense->expense_date ?? now()->toDateString();
            $expenseName = $expense->name ?? 'Pengeluaran Operasional';
            if($expenseAccount){
                JournalEntry::create([
                    'account_id' => $expenseAccount->id,
                    'date' => $expenseDate,
                    'description' => 'Beban: ' . $expenseName,
                    'debit' => $totalAmount,
                    'credit'=> 0,
                ]);
            }
            if ($cashAccount){
                JournalEntry::create([
                    'account_id' => $cashAccount->id,
                    'date' => $expenseDate,
                    'description' => 'Pembayaran Kas Untuk: ' . $expenseName,
                    'debit' => 0,
                    'credit'=> $totalAmount,
                ]);
            }
        }
    }
