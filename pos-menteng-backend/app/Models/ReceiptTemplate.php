<?php

namespace App\Models;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptTemplate extends Model
{
    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'business_name',
        'address',
        'phone',
        'logo_url',
        'paper_width',
        'show_cashier',
        'show_customer',
        'show_order_type',
        'show_tax',
        'show_discount',
        'show_sku',
        'show_change',
        'footer_text',
        'wifi_text',
        'is_active',
    ];

    protected $casts = [
        'show_cashier' => 'boolean',
        'show_customer' => 'boolean',
        'show_order_type' => 'boolean',
        'show_tax' => 'boolean',
        'show_discount' => 'boolean',
        'show_sku' => 'boolean',
        'show_change' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
