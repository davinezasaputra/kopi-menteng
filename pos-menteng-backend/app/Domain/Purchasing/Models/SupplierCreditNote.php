<?php

namespace App\Domain\Purchasing\Models;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierCreditNote extends Model
{
    protected $fillable = [
        'tenant_id','company_id','branch_id','supplier_id','supplier_return_id','supplier_invoice_id',
        'credit_note_number','credit_note_date','amount','applied_amount','remaining_amount',
        'status','created_by','request_id','reason','notes',
    ];

    protected function casts(): array
    {
        return [
            'credit_note_date'=>'date',
            'amount'=>'decimal:2',
            'applied_amount'=>'decimal:2',
            'remaining_amount'=>'decimal:2',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function supplierReturn(): BelongsTo { return $this->belongsTo(SupplierReturn::class,'supplier_return_id'); }
    public function supplierInvoice(): BelongsTo { return $this->belongsTo(SupplierInvoice::class,'supplier_invoice_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }
}
