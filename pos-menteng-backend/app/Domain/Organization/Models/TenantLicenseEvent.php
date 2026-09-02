<?php

namespace App\Domain\Organization\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantLicenseEvent extends Model
{
    protected $fillable = [
        'tenant_id','tenant_license_id','actor_user_id','event',
        'from_plan_code','to_plan_code','from_status','to_status','metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function license(): BelongsTo { return $this->belongsTo(TenantLicense::class, 'tenant_license_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_user_id'); }
}
