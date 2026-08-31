<?php

namespace App\Domain\Purchasing\Models;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierInvoice extends Model
{
    protected $fillable = [
        'tenant_id','company_id','branch_id','supplier_id','purchase_order_id','goods_receipt_id',
        'invoice_number','invoice_date','due_date','subtotal','tax_amount','discount_amount',
        'total_amount','paid_amount','status','created_by','request_id','notes',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date'=>'date','due_date'=>'date',
            'subtotal'=>'decimal:2','tax_amount'=>'decimal:2','discount_amount'=>'decimal:2',
            'total_amount'=>'decimal:2','paid_amount'=>'decimal:2',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function order(): BelongsTo { return $this->belongsTo(PurchaseOrder::class,'purchase_order_id'); }
    public function goodsReceipt(): BelongsTo { return $this->belongsTo(GoodsReceipt::class,'goods_receipt_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }
}
