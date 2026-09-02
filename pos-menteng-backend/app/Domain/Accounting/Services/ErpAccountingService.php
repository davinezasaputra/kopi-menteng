<?php

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Models\ErpAccount;
use App\Domain\Accounting\Models\ErpJournalBatch;
use App\Domain\Accounting\Models\ErpJournalLine;
use App\Domain\Core\Services\DocumentNumberService;
use App\Domain\Organization\Models\Branch;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ErpAccountingService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly DocumentNumberService $numbers,
        private readonly FinanceClosingService $closing,
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

    private function resolveBranchId(array $data, $membership): int
    {
        $branchId = (int)($data['branch_id'] ?? $membership->branch_id);

        $branch = Branch::query()
            ->whereKey($branchId)
            ->where('company_id', $membership->company_id)
            ->whereHas('company', fn ($query) => $query->where('tenant_id', $membership->tenant_id))
            ->first();

        if (! $branch) {
            throw ValidationException::withMessages(['branch_id' => 'Branch is outside the active organization scope.']);
        }

        if ((int)$branch->id !== (int)$membership->branch_id) {
            throw ValidationException::withMessages(['branch_id' => 'Journal branch must match the active branch context.']);
        }

        return (int)$branch->id;
    }

    public function postJournal(array $data): ErpJournalBatch
    {
        $membership = $this->context->membership();

        if (! $membership) {
            throw ValidationException::withMessages(['context' => 'No active ERP context.']);
        }

        $branchId = $this->resolveBranchId($data, $membership);
        $journalDate = $data['journal_date'] ?? now()->toDateString();
        $this->closing->assertOpenForDate($journalDate, $membership->tenant_id, $membership->company_id);

        $lines = $data['lines'] ?? null;
        if (! is_array($lines) || count($lines) < 2) {
            throw ValidationException::withMessages([
                'lines' => 'Journal requires at least two lines.',
            ]);
        }

        $normalizedLines = [];
        foreach ($lines as $line) {
            $debitRaw = $line['debit'] ?? 0;
            $creditRaw = $line['credit'] ?? 0;

            if (! is_numeric($debitRaw) || ! is_numeric($creditRaw)) {
                throw ValidationException::withMessages([
                    'lines' => 'Journal debit and credit amounts must be numeric.',
                ]);
            }

            $debit = round((float) $debitRaw, 2);
            $credit = round((float) $creditRaw, 2);

            if (! is_finite($debit) || ! is_finite($credit) || $debit < 0 || $credit < 0) {
                throw ValidationException::withMessages([
                    'lines' => 'Journal amounts must be finite and non-negative.',
                ]);
            }

            if (($debit > 0 && $credit > 0) || ($debit === 0.0 && $credit === 0.0)) {
                throw ValidationException::withMessages([
                    'lines' => 'Each journal line must contain exactly one positive amount.',
                ]);
            }

            $normalizedLine = $line;
            $normalizedLine['debit'] = $debit;
            $normalizedLine['credit'] = $credit;
            $normalizedLines[] = $normalizedLine;
        }

        $lines = $normalizedLines;
        $debit = round(collect($lines)->sum('debit'), 2);
        $credit = round(collect($lines)->sum('credit'), 2);

        if ($debit <= 0 || $credit <= 0 || abs($debit - $credit) > 0.009) {
            throw ValidationException::withMessages(['lines' => 'Journal must be balanced with positive debit and credit totals.']);
        }

        foreach ($lines as $line) {
            $account = ErpAccount::query()
                ->where('tenant_id', $membership->tenant_id)
                ->where('company_id', $membership->company_id)
                ->findOrFail($line['account_id']);

            if (! $account->is_active || ! $account->is_postable) {
                throw ValidationException::withMessages(['lines' => "Account {$account->code} is not postable."]);
            }
        }

        return DB::transaction(function () use ($membership, $data, $lines, $debit, $credit, $branchId, $journalDate) {
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

            $createdBy = $data['created_by'] ?? auth()->id();
            if ($createdBy === null) {
                throw ValidationException::withMessages([
                    'created_by' => 'Journal creator is required when no authenticated user is available.',
                ]);
            }

            $batch = ErpJournalBatch::create([
                'tenant_id' => $membership->tenant_id,
                'company_id' => $membership->company_id,
                'branch_id' => $branchId,
                'journal_number' => $data['journal_number'] ?? $this->numbers->next('erp_journal', 'JRN'),
                'journal_date' => $journalDate,
                'status' => 'posted',
                'source_type' => $data['source_type'] ?? 'manual',
                'source_id' => $data['source_id'] ?? null,
                'description' => $data['description'],
                'total_debit' => $debit,
                'total_credit' => $credit,
                'created_by' => $createdBy,
                'request_id' => $requestId,
            ]);

            foreach ($lines as $line) {
                ErpJournalLine::create([
                    'journal_batch_id' => $batch->id,
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
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
        ?string $journalDate = null,
        ?int $createdBy = null,
    ): ErpJournalBatch {
        $membership = $this->context->membership();

        if (! $membership) {
            throw ValidationException::withMessages(['context' => 'No active ERP context.']);
        }

        return DB::transaction(function () use ($membership, $sourceType, $sourceId, $description, $lines, $branchId, $journalDate, $createdBy) {
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

            if ($journalDate === null) {
                throw ValidationException::withMessages([
                    'journal_date' => 'Automatic journal posting requires the source business date.',
                ]);
            }

            return $this->postJournal([
                'branch_id' => $branchId ?? $membership->branch_id,
                'journal_date' => $journalDate,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'description' => $description,
                'lines' => $lines,
                'created_by' => $createdBy,
            ]);
        });
    }
}
