<?php

namespace App\Domain\Purchasing\Models;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id','product_id','quantity','unit_cost','discount_amount',
        'tax_amount','line_total','received_quantity','notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity'=>'decimal:4','unit_cost'=>'decimal:4','discount_amount'=>'decimal:2',
            'tax_amount'=>'decimal:2','line_total'=>'decimal:2','received_quantity'=>'decimal:4',
        ];
    }

    public function order(): BelongsTo { return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
