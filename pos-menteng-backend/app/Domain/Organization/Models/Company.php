<?php

namespace App\Domain\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = ['tenant_id','code','name','legal_name','tax_number','email','phone','address','timezone','currency','status'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function branches(): HasMany { return $this->hasMany(Branch::class); }
    public function departments(): HasMany { return $this->hasMany(Department::class); }
    public function costCenters(): HasMany { return $this->hasMany(CostCenter::class); }
}
