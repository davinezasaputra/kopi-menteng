<?php

namespace App\Domain\Sales\Models;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Sales\Models\SalesShipment;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SalesInvoice extends Model
{
    protected $table='sales_invoices';
    public $incrementing=false;
    protected $keyType='string';

    protected $fillable=[
        'id','tenant_id','company_id','branch_id','sales_order_id','sales_shipment_id',
        'customer_id','customer_name_snapshot','invoice_number','invoice_date','due_date',
        'subtotal','discount_amount','tax_amount','total_amount','paid_amount',
        'outstanding_amount','status','created_by','request_id','notes',
    ];

    protected static function booted(): void
    {
        static::creating(fn(self $row)=>$row->id ??= (string)Str::uuid());
    }

    protected function casts(): array
    {
        return [
            'invoice_date'=>'date',
            'due_date'=>'date',
            'subtotal'=>'decimal:2',
            'discount_amount'=>'decimal:2',
            'tax_amount'=>'decimal:2',
            'total_amount'=>'decimal:2',
            'paid_amount'=>'decimal:2',
            'outstanding_amount'=>'decimal:2',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function salesOrder(): BelongsTo { return $this->belongsTo(SalesOrder::class,'sales_order_id'); }
    public function salesShipment(): BelongsTo { return $this->belongsTo(SalesShipment::class,'sales_shipment_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }
    public function items(): HasMany { return $this->hasMany(SalesInvoiceItem::class); }
}
