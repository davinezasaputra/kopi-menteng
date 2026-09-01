<?php

namespace App\Domain\Purchasing\Models;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchasingBudget extends Model
{
    protected $fillable = [
        'tenant_id','company_id','branch_id','budget_year','allocated_amount',
        'committed_amount','spent_amount','is_active','created_by','updated_by','notes',
    ];

    protected function casts(): array
    {
        return [
            'budget_year'=>'integer',
            'allocated_amount'=>'decimal:2',
            'committed_amount'=>'decimal:2',
            'spent_amount'=>'decimal:2',
            'is_active'=>'boolean',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class,'updated_by'); }
}
