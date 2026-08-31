<?php

namespace App\Domain\Core\Services;

use App\Domain\Core\Models\DocumentSequence;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    public function next(string $documentType, string $prefix, ?string $period = null): string
    {
        $context = app(TenantContext::class);
        $period ??= now()->format('Ym');

        return DB::transaction(function () use ($documentType, $prefix, $period, $context) {
            $sequence = DocumentSequence::query()
                ->where('tenant_id', $context->tenantId())
                ->where('company_id', $context->companyId())
                ->where('branch_id', $context->branchId())
                ->where('document_type', $documentType)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = DocumentSequence::create([
                    'tenant_id' => $context->tenantId(),
                    'company_id' => $context->companyId(),
                    'branch_id' => $context->branchId(),
                    'document_type' => $documentType,
                    'prefix' => $prefix,
                    'period' => $period,
                    'next_number' => 1,
                    'padding' => 6,
                ]);
            }

            $number = $sequence->next_number;
            $sequence->increment('next_number');

            return sprintf('%s-%s-%0'.$sequence->padding.'d', $sequence->prefix, $period, $number);
        });
    }
}
