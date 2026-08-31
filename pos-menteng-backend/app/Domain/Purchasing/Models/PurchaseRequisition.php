<?php

namespace App\Domain\Purchasing\Models;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use App\Domain\Organization\Models\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequisition extends Model
{
    protected $fillable = [
        'tenant_id','company_id','branch_id','warehouse_id','requisition_number',
        'status','needed_by','reason','requested_by','submitted_by','submitted_at',
        'cancelled_by','cancelled_at','request_id','notes',
    ];

    protected function casts(): array
    {
        return ['needed_by'=>'date','submitted_at'=>'datetime','cancelled_at'=>'datetime'];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function submitter(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by'); }
    public function canceller(): BelongsTo { return $this->belongsTo(User::class, 'cancelled_by'); }
    public function items(): HasMany { return $this->hasMany(PurchaseRequisitionItem::class); }
}
