<?php

namespace App\Observers;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Payroll;

class PayrollObserver
{
    public function created(Payroll $payroll): void
    {
        if ($payroll->is_paid) {
            $this->recordToJournal($payroll);
        }
    }

    public function updated(Payroll $payroll): void
    {
        if ($payroll->isDirty('is_paid') && $payroll->is_paid) {
            $this->recordToJournal($payroll);
        }
    }

    private function recordToJournal(Payroll $payroll): void
    {
        $totalAmount = (float) ($payroll->total_salary ?? 0);

        if ($totalAmount <= 0) {
            return;
        }

        $salaryExpenseAccount = Account::where('code', '521')->first()
            ?? Account::where('type', 'expense')->first();

        $cashAccount = Account::where('code', '101')->first()
            ?? Account::where('type', 'asset')->first();

        $employeeName = $payroll->employee?->name ?? 'Karyawan';
        $date = $payroll->updated_at?->toDateString() ?? $payroll->created_at?->toDateString() ?? now()->toDateString();

        if ($salaryExpenseAccount) {
            JournalEntry::create([
                'account_id' => $salaryExpenseAccount->id,
                'date' => $date,
                'description' => 'Beban Gaji Karyawan: ' . $employeeName . ' (Periode ' . $payroll->period . ')',
                'debit' => $totalAmount,
                'credit' => 0,
            ]);
        }

        if ($cashAccount) {
            JournalEntry::create([
                'account_id' => $cashAccount->id,
                'date' => $date,
                'description' => 'Pembayaran Gaji Karyawan: ' . $employeeName . ' (Periode ' . $payroll->period . ')',
                'debit' => 0,
                'credit' => $totalAmount,
            ]);
        }
    }
}
