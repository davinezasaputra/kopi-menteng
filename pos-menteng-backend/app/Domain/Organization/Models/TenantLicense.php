<?php

namespace App\Domain\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantLicense extends Model
{
    protected $fillable = [
        'tenant_id','plan_code','plan_name','features','max_users','max_branches',
        'starts_at','expires_at','status','auto_renew','notes',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'auto_renew' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') return false;
        if ($this->starts_at && now()->lt($this->starts_at)) return false;
        if ($this->expires_at && now()->gt($this->expires_at)) return false;
        return true;
    }

    public function hasFeature(string $feature): bool
    {
        return $this->isActive() && in_array($feature, $this->features ?? [], true);
    }

    public function allowsPermission(string $permission): bool
    {
        if (! $this->isActive()) return false;

        $prefixMap = [
            'pos.' => 'pos',
            'inventory.' => 'inventory',
            'purchasing.' => 'purchasing',
            'sales.' => 'sales',
            'accounting.' => 'accounting',
            'hr.' => 'hrm',
            'users.' => 'administration',
            'rbac.' => 'administration',
            'audit.' => 'audit',
            'organization.' => 'organization',
        ];

        foreach ($prefixMap as $prefix => $feature) {
            if (str_starts_with($permission, $prefix)) return $this->hasFeature($feature);
        }

        return false;
    }
}
