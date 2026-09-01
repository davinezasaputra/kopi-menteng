<?php

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Membership extends Model
{
    protected $fillable = ['tenant_id','user_id','company_id','branch_id','role_id','status','is_primary'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(\App\Domain\Organization\Models\Tenant::class); }
    public function user(): BelongsTo { return $this->belongsTo(\App\Models\User::class); }
    public function company(): BelongsTo { return $this->belongsTo(\App\Domain\Organization\Models\Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(\App\Domain\Organization\Models\Branch::class); }
    public function role(): BelongsTo { return $this->belongsTo(Role::class); }
    public function permissionOverrides(): HasMany { return $this->hasMany(MembershipPermission::class); }
}
