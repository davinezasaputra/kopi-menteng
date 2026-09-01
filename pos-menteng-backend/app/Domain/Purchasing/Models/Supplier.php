<?php

namespace App\Domain\Purchasing\Models;

use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use App\Domain\Purchasing\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'tenant_id','company_id','code','name','tax_id','contact_name',
        'phone','email','address','payment_terms_days','status',
    ];

    protected function casts(): array
    {
        return ['payment_terms_days' => 'integer'];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function purchaseOrders(): HasMany { return $this->hasMany(PurchaseOrder::class); }
}
