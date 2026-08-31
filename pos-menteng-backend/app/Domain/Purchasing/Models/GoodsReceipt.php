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

class GoodsReceipt extends Model
{
    protected $fillable = [
        'tenant_id','company_id','branch_id','warehouse_id','supplier_id',
        'purchase_order_id','receipt_number','receipt_date','status','received_by',
        'request_id','notes',
    ];

    protected function casts(): array
    {
        return ['receipt_date'=>'date'];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function order(): BelongsTo { return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
    public function receiver(): BelongsTo { return $this->belongsTo(User::class, 'received_by'); }
    public function items(): HasMany { return $this->hasMany(GoodsReceiptItem::class); }
}
