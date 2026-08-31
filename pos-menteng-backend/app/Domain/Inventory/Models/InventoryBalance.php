<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use App\Domain\Organization\Models\Warehouse;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryBalance extends Model
{
    protected $fillable = [
        'tenant_id', 'company_id', 'branch_id', 'warehouse_id',
        'product_id', 'quantity', 'reserved_quantity', 'average_cost', 'last_cost',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'reserved_quantity' => 'decimal:4',
        'average_cost' => 'decimal:4',
        'last_cost' => 'decimal:4',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
