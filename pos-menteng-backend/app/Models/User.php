<?php

namespace App\Models;

use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\Role;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, HasApiTokens, Notifiable;

    protected $fillable = [
        'name','email','password','role','pin','pin_hash','pin_lookup',
        'default_tenant_id','default_company_id','default_branch_id',
    ];

    protected $hidden = ['password','remember_token','pin','pin_hash','pin_lookup'];

    protected function casts(): array { return ['email_verified_at'=>'datetime','password'=>'hashed']; }
    public function shifts(): HasMany { return $this->hasMany(Shift::class); }
    public function payroll(): HasMany { return $this->hasMany(Payroll::class); }
    public function memberships(): HasMany { return $this->hasMany(Membership::class); }
    public function roles(): BelongsToMany { return $this->belongsToMany(Role::class,'memberships','user_id','role_id')->withPivot(['tenant_id','company_id','branch_id','status','is_primary']); }
    public function defaultTenant(): BelongsTo { return $this->belongsTo(Tenant::class,'default_tenant_id'); }
    public function defaultCompany(): BelongsTo { return $this->belongsTo(Company::class,'default_company_id'); }
    public function defaultBranch(): BelongsTo { return $this->belongsTo(Branch::class,'default_branch_id'); }
    public function hasPermission(string $permission): bool { return app(\App\Support\Auth\PermissionService::class)->hasPermission($this,$permission); }

    public function setPinAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['pin'] = null;
            $this->attributes['pin_hash'] = null;
            $this->attributes['pin_lookup'] = null;
            return;
        }
        $pin=(string)$value;
        $this->attributes['pin_hash']=Hash::make($pin);
        $this->attributes['pin_lookup']=hash_hmac('sha256',$pin,(string)config('app.key'));
        $this->attributes['pin']=null;
    }
}
