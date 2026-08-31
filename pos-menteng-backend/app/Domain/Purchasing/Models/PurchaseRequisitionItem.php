<?php

namespace App\Domain\Purchasing\Models;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequisitionItem extends Model
{
    protected $fillable = [
        'purchase_requisition_id','product_id','quantity','estimated_unit_cost','notes',
    ];

    protected function casts(): array
    {
        return ['quantity'=>'decimal:4','estimated_unit_cost'=>'decimal:4'];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'purchase_requisition_id');
    }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
