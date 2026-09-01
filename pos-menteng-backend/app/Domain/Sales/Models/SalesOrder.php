<?php

namespace App\Domain\Sales\Models;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Inventory\Models\InventoryReservation;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SalesOrder extends Model
{
    protected $table = 'sales_orders';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id','tenant_id','company_id','branch_id','location_id','warehouse_id','customer_id','customer_name_snapshot',
        'order_number','order_date','status','subtotal','discount_amount','tax_amount','grand_total',
        'created_by','submitted_by','submitted_at','approved_by','approved_at','rejected_by','rejected_at','rejection_reason','approval_matrix_rule_id','inventory_reservation_id','cancelled_by','cancelled_at','request_id','notes',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $order) { $order->id ??= (string) Str::uuid(); });
    }

    protected function casts(): array
    {
        return ['order_date'=>'date','submitted_at'=>'datetime','cancelled_at'=>'datetime','subtotal'=>'decimal:2','discount_amount'=>'decimal:2','tax_amount'=>'decimal:2','grand_total'=>'decimal:2'];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }
    public function submitter(): BelongsTo { return $this->belongsTo(User::class,'submitted_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class,'approved_by'); }
    public function rejecter(): BelongsTo { return $this->belongsTo(User::class,'rejected_by'); }
    public function approvalRule(): BelongsTo { return $this->belongsTo(SalesApprovalMatrixRule::class,'approval_matrix_rule_id'); }
    public function inventoryReservation(): BelongsTo { return $this->belongsTo(InventoryReservation::class,'inventory_reservation_id'); }
    public function canceller(): BelongsTo { return $this->belongsTo(User::class,'cancelled_by'); }
    public function items(): HasMany { return $this->hasMany(SalesOrderItem::class); }
}
