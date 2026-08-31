<?php

namespace App\Domain\Audit\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditService
{
    public function record(string $event, string $module, ?Model $entity = null, ?array $oldValues = null, ?array $newValues = null, ?Request $request = null): AuditLog
    {
        $context = app(TenantContext::class);
        $request ??= request();

        return AuditLog::create([
            'tenant_id' => $context->tenantId(),
            'user_id' => $request?->user()?->getAuthIdentifier(),
            'company_id' => $context->companyId(),
            'branch_id' => $context->branchId(),
            'event' => $event,
            'module' => $module,
            'entity_type' => $entity ? $entity::class : null,
            'entity_id' => $entity?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'request_id' => $request?->attributes->get('request_id'),
            'created_at' => now(),
        ]);
    }
}
