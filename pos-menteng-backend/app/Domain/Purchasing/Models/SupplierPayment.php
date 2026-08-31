<?php

namespace App\Domain\Purchasing\Models;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPayment extends Model
{
    protected $fillable = [
        'tenant_id','company_id','branch_id','supplier_id','supplier_invoice_id',
        'payment_number','payment_date','amount','method','reference','paid_by',
        'request_id','notes',
    ];

    protected function casts(): array
    {
        return ['payment_date'=>'date','amount'=>'decimal:2'];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(SupplierInvoice::class,'supplier_invoice_id'); }
    public function payer(): BelongsTo { return $this->belongsTo(User::class,'paid_by'); }
}
