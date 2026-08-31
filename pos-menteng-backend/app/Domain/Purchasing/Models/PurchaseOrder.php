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

class PurchaseOrder extends Model
{
    protected $fillable = [
        'tenant_id','company_id','branch_id','warehouse_id','supplier_id','purchase_requisition_id',
        'order_number','status','order_date','expected_date','subtotal','discount_amount',
        'tax_amount','grand_total','created_by','submitted_by','submitted_at','approved_by',
        'approved_at','cancelled_by','cancelled_at','request_id','notes',
    ];

    protected function casts(): array
    {
        return [
            'order_date'=>'date','expected_date'=>'date','submitted_at'=>'datetime',
            'approved_at'=>'datetime','cancelled_at'=>'datetime',
            'subtotal'=>'decimal:2','discount_amount'=>'decimal:2',
            'tax_amount'=>'decimal:2','grand_total'=>'decimal:2',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function requisition(): BelongsTo { return $this->belongsTo(PurchaseRequisition::class, 'purchase_requisition_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function submitter(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function canceller(): BelongsTo { return $this->belongsTo(User::class, 'cancelled_by'); }
    public function items(): HasMany { return $this->hasMany(PurchaseOrderItem::class); }
}
