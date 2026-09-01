<?php

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Models\ErpAccount;
use App\Domain\Accounting\Models\ErpJournalBatch;
use App\Domain\Accounting\Models\ErpJournalLine;
use App\Domain\Core\Services\DocumentNumberService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ErpAccountingService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly DocumentNumberService $numbers,
    ) {
    }

    public function createAccount(array $data): ErpAccount
    {
        $membership = $this->context->membership();

        if (! $membership) {
            throw ValidationException::withMessages(['context' => 'No active ERP context.']);
        }

        if (! in_array($data['type'], ['asset','liability','equity','revenue','expense'], true)) {
            throw ValidationException::withMessages(['type' => 'Invalid account type.']);
        }

        if (! in_array($data['normal_balance'], ['debit','credit'], true)) {
            throw ValidationException::withMessages(['normal_balance' => 'Invalid normal balance.']);
        }

        if (!empty($data['parent_id'])) {
            $parent = ErpAccount::query()
                ->where('tenant_id', $membership->tenant_id)
                ->where('company_id', $membership->company_id)
                ->findOrFail($data['parent_id']);

            if (! $parent->is_active) {
                throw ValidationException::withMessages(['parent_id' => 'Parent account is inactive.']);
            }
        }

        return ErpAccount::create([
            'tenant_id' => $membership->tenant_id,
            'company_id' => $membership->company_id,
            'code' => $data['code'],
            'name' => $data['name'],
            'type' => $data['type'],
            'normal_balance' => $data['normal_balance'],
            'parent_id' => $data['parent_id'] ?? null,
            'is_postable' => $data['is_postable'] ?? true,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function postJournal(array $data): ErpJournalBatch
    {
        $membership = $this->context->membership();

        if (! $membership) {
            throw ValidationException::withMessages(['context' => 'No active ERP context.']);
        }

        $lines = $data['lines'];
        $debit = round(collect($lines)->sum(fn ($line) => (float)($line['debit'] ?? 0)), 2);
        $credit = round(collect($lines)->sum(fn ($line) => (float)($line['credit'] ?? 0)), 2);

        if ($debit <= 0 || $credit <= 0 || abs($debit - $credit) > 0.009) {
            throw ValidationException::withMessages(['lines' => 'Journal must be balanced with positive debit and credit totals.']);
        }

        foreach ($lines as $line) {
            if (((float)($line['debit'] ?? 0) > 0) && ((float)($line['credit'] ?? 0) > 0)) {
                throw ValidationException::withMessages(['lines' => 'A journal line cannot contain both debit and credit.']);
            }

            $account = ErpAccount::query()
                ->where('tenant_id', $membership->tenant_id)
                ->where('company_id', $membership->company_id)
                ->findOrFail($line['account_id']);

            if (! $account->is_active || ! $account->is_postable) {
                throw ValidationException::withMessages(['lines' => "Account {$account->code} is not postable."]);
            }
        }

        return DB::transaction(function () use ($membership, $data, $lines, $debit, $credit) {
            $requestId = request()->attributes->get('request_id');

            if ($requestId) {
                $existing = ErpJournalBatch::query()
                    ->where('tenant_id', $membership->tenant_id)
                    ->where('request_id', $requestId)
                    ->with('lines.account')
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            $batch = ErpJournalBatch::create([
                'tenant_id' => $membership->tenant_id,
                'company_id' => $membership->company_id,
                'branch_id' => $data['branch_id'] ?? $membership->branch_id,
                'journal_number' => $data['journal_number'] ?? $this->numbers->next('erp_journal', 'JRN'),
                'journal_date' => $data['journal_date'] ?? now()->toDateString(),
                'status' => 'posted',
                'source_type' => $data['source_type'] ?? 'manual',
                'source_id' => $data['source_id'] ?? null,
                'description' => $data['description'],
                'total_debit' => $debit,
                'total_credit' => $credit,
                'created_by' => auth()->id(),
                'request_id' => $requestId,
            ]);

            foreach ($lines as $line) {
                ErpJournalLine::create([
                    'journal_batch_id' => $batch->id,
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'description' => $line['description'] ?? null,
                ]);
            }

            return $batch->load('lines.account');
        });
    }
    public function postSourceJournal(
        string $sourceType,
        string $sourceId,
        string $description,
        array $lines,
        ?int $branchId = null,
    ): ErpJournalBatch {
        $membership = $this->context->membership();

        if (! $membership) {
            throw ValidationException::withMessages(['context' => 'No active ERP context.']);
        }

        return DB::transaction(function () use ($membership, $sourceType, $sourceId, $description, $lines, $branchId) {
            $existing = ErpJournalBatch::query()
                ->where('tenant_id', $membership->tenant_id)
                ->where('company_id', $membership->company_id)
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->with('lines.account')
                ->first();

            if ($existing) {
                return $existing;
            }

            return $this->postJournal([
                'branch_id' => $branchId ?? $membership->branch_id,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'description' => $description,
                'lines' => $lines,
            ]);
        });
    }

}
