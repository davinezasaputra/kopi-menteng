<?php

namespace App\Domain\Core\Services;

use App\Domain\Core\Enums\DocumentType;
use App\Domain\Core\Models\DocumentSequence;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

class DocumentSequenceService
{
    public function __construct(private readonly TenantContext $context) {}

    public function next(DocumentType|string $type, ?string $prefix = null, ?int $padding = null): string
    {
        $type = $type instanceof DocumentType ? $type->value : $type;
        $tenantId = $this->context->tenantId();
        if ($tenantId === null) throw new \RuntimeException('Tenant context is required.');

        return DB::transaction(function () use ($type, $prefix, $padding, $tenantId) {
            $companyId = $this->context->companyId();
            $branchId = $this->context->branchId();
            $period = now()->format('Ym');
            $lockKey = implode(':', [$tenantId, $companyId ?? 'null', $branchId ?? 'null', $type, $period]);

            // PostgreSQL advisory transaction lock closes the race where two callers
            // try to create the first sequence row simultaneously. Row locking then
            // serializes subsequent increments. SQLite/test drivers simply use the
            // transaction + row lock path.
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', [$lockKey]);
            }

            $query = DocumentSequence::query()
                ->where('tenant_id', $tenantId)
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('document_type', $type)
                ->where('period', $period);

            $sequence = $query->lockForUpdate()->first();

            if (! $sequence) {
                $sequence = DocumentSequence::create([
                    'tenant_id'=>$tenantId,
                    'company_id'=>$companyId,
                    'branch_id'=>$branchId,
                    'document_type'=>$type,
                    'prefix'=>$prefix ?: strtoupper(str_replace('_','',$type)),
                    'period'=>$period,
                    'next_number'=>1,
                    'padding'=>$padding ?: 6,
                ]);
            }

            $number=(int)$sequence->next_number;
            $sequence->increment('next_number');
            return sprintf('%s-%s-%s',$sequence->prefix,$period,str_pad((string)$number,$sequence->padding,'0',STR_PAD_LEFT));
        });
    }
}
