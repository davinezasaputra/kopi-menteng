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
            $scope = [
                'tenant_id' => $context->tenantId(),
                'company_id' => $context->companyId(),
                'branch_id' => $context->branchId(),
                'document_type' => $documentType,
                'period' => $period,
            ];

            DB::table('document_sequences')->insertOrIgnore($scope + [
                'prefix' => $prefix,
                'next_number' => 1,
                'padding' => 6,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DocumentSequence::query()->where($scope)->lockForUpdate()->firstOrFail();
            $number = $sequence->next_number;
            $sequence->increment('next_number');

            return sprintf('%s-%s-%0'.$sequence->padding.'d', $sequence->prefix, $period, $number);
        });
    }
}
