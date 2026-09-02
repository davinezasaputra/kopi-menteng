<?php

namespace App\Http\Controllers\Api;

use App\Domain\Accounting\Models\ErpAccount;
use App\Domain\Accounting\Models\ErpJournalBatch;
use App\Domain\Accounting\Services\ErpAccountingService;
use App\Http\Controllers\Controller;
use App\Services\PermissionService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountingController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly ErpAccountingService $service,
        private readonly PermissionService $permissions,
    ) {
    }

    public function accounts(): JsonResponse
    {
        $this->requirePermission('accounting.erp_account.view');

        $accounts = ErpAccount::query()
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->companyId())
            ->withSum('journalLines as total_debit', 'debit')
            ->withSum('journalLines as total_credit', 'credit')
            ->orderBy('code')
            ->get()
            ->map(function (ErpAccount $account) {
                $debit = (float) ($account->total_debit ?? 0);
                $credit = (float) ($account->total_credit ?? 0);
                $account->setAttribute(
                    'balance',
                    in_array($account->type, ['asset', 'expense'], true)
                        ? $debit - $credit
                        : $credit - $debit
                );
                return $account;
            });

        return response()->json(['status' => 'success', 'data' => $accounts]);
    }

    public function addAccount(Request $request): JsonResponse
    {
        $this->requirePermission('accounting.erp_account.create');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:asset,liability,equity,revenue,expense'],
            'normal_balance' => ['nullable', 'in:debit,credit'],
        ]);

        $validated['normal_balance'] ??= in_array($validated['type'], ['asset', 'expense'], true)
            ? 'debit'
            : 'credit';

        $account = $this->service->createAccount($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Kode akun ERP berhasil dibuat.',
            'data' => $account,
        ], 201);
    }

    public function journals(): JsonResponse
    {
        $this->requirePermission('accounting.erp_journal.view');

        $batches = ErpJournalBatch::query()
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->companyId())
            ->with('lines.account:id,code,name')
            ->latest('journal_date')
            ->latest('id')
            ->limit(100)
            ->get();

        $rows = $batches->flatMap(function (ErpJournalBatch $batch) {
            return $batch->lines->map(function ($line) use ($batch) {
                return [
                    'id' => $line->id,
                    'journal_id' => $batch->id,
                    'date' => $batch->journal_date,
                    'journal_number' => $batch->journal_number,
                    'description' => $line->description ?: $batch->description,
                    'account_id' => $line->account_id,
                    'account' => $line->account,
                    'debit' => $line->debit,
                    'credit' => $line->credit,
                    'status' => $batch->status,
                ];
            });
        })->values();

        return response()->json(['status' => 'success', 'data' => $rows]);
    }

    public function addJournal(Request $request): JsonResponse
    {
        $this->requirePermission('accounting.erp_journal.create');

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'journal_number' => ['nullable', 'string', 'max:80'],
            'journal_date' => ['nullable', 'date'],
            'date' => ['nullable', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:erp_accounts,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'gte:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'gte:0'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ]);

        $data['journal_date'] ??= $data['date'] ?? null;
        unset($data['date']);

        $journal = $this->service->postJournal($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Jurnal ERP berhasil dicatat.',
            'data' => $journal,
        ], 201);
    }

    private function requirePermission(string $permission): void
    {
        $user = request()->user();

        if (! $user || ! $this->permissions->hasPermission($user, $permission)) {
            abort(403, 'Insufficient permission.');
        }
    }
}
