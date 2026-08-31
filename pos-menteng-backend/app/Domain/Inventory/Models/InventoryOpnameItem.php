<?php

namespace App\Domain\Inventory\Models;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryOpnameItem extends Model
{
    protected $fillable = [
        'inventory_opname_id', 'product_id',
        'system_quantity', 'counted_quantity', 'variance', 'unit_cost',
    ];

    protected $casts = [
        'system_quantity' => 'decimal:4',
        'counted_quantity' => 'decimal:4',
        'variance' => 'decimal:4',
        'unit_cost' => 'decimal:4',
    ];

    public function opname(): BelongsTo { return $this->belongsTo(InventoryOpname::class, 'inventory_opname_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
