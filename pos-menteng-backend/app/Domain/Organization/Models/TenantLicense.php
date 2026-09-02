<?php

namespace App\Domain\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantLicense extends Model
{
    protected $fillable = [
        'tenant_id','plan_code','plan_name','features',
        'max_users','max_companies','max_branches','max_locations',
        'starts_at','expires_at','status','auto_renew','notes',
    ];

    private const PLAN_LIMITS = [
        'starter' => ['max_users' => 5, 'max_companies' => 1, 'max_branches' => 1, 'max_locations' => 3],
        'business' => ['max_users' => 20, 'max_companies' => 3, 'max_branches' => 5, 'max_locations' => 15],
        'professional' => ['max_users' => 50, 'max_companies' => 10, 'max_branches' => 15, 'max_locations' => 50],
        'enterprise' => ['max_users' => null, 'max_companies' => null, 'max_branches' => null, 'max_locations' => null],
    ];

    protected static function booted(): void
    {
        static::creating(function (self $license): void {
            $license->applyPlanDefaults();
        });

        static::updating(function (self $license): void {
            if ($license->isDirty('plan_code')) {
                $license->applyPlanDefaults(true);
            }
        });
    }

    private function applyPlanDefaults(bool $overwrite = false): void
    {
        $defaults = self::PLAN_LIMITS[$this->plan_code] ?? null;
        if (! $defaults) return;

        foreach ($defaults as $field => $value) {
            if ($overwrite && $this->isDirty($field)) continue;
            if ($overwrite || $this->getAttribute($field) === null) {
                $this->setAttribute($field, $value);
            }
        }
    }

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

        $subscription = TenantSubscription::query()->where('tenant_id', $this->tenant_id)->latest('id')->first();
        if ($subscription) {
            if (in_array($subscription->status, ['suspended', 'cancelled'], true)) return false;
            if ($subscription->status === 'past_due' && (! $subscription->grace_until || now()->gt($subscription->grace_until))) return false;
        }

        return true;
    }

    public function hasFeature(string $feature): bool
    {
        return $this->isActive() && in_array($feature, $this->features ?? [], true);
    }

    public function allowsPermission(string $permission): bool
    {
        if (! $this->isActive()) return false;
        if (str_starts_with($permission, 'organization.')) return true;

        $prefixMap = [
            'pos.' => 'pos', 'inventory.' => 'inventory', 'purchasing.' => 'purchasing',
            'sales.' => 'sales', 'accounting.' => 'accounting', 'hr.' => 'hrm',
            'users.' => 'administration', 'rbac.' => 'administration', 'audit.' => 'audit',
        ];

        foreach ($prefixMap as $prefix => $feature) {
            if (str_starts_with($permission, $prefix)) return $this->hasFeature($feature);
        }

        return false;
    }
}
