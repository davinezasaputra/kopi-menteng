<?php

namespace App\Domain\Purchasing\Models;

use App\Domain\Identity\Models\Role;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchasingApprovalMatrixRule extends Model
{
    protected $fillable = [
        'tenant_id','company_id','branch_id','approver_role_id','document_type',
        'min_amount','max_amount','priority','is_active','notes',
    ];

    protected function casts(): array
    {
        return [
            'min_amount'=>'decimal:2',
            'max_amount'=>'decimal:2',
            'priority'=>'integer',
            'is_active'=>'boolean',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function approverRole(): BelongsTo { return $this->belongsTo(Role::class,'approver_role_id'); }
}
