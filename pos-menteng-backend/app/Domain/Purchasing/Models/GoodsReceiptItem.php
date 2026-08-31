<?php

namespace App\Domain\Purchasing\Models;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptItem extends Model
{
    protected $fillable = [
        'goods_receipt_id','purchase_order_item_id','product_id',
        'ordered_quantity','received_quantity','unit_cost','line_value',
    ];

    protected function casts(): array
    {
        return [
            'ordered_quantity'=>'decimal:4',
            'received_quantity'=>'decimal:4',
            'unit_cost'=>'decimal:4',
            'line_value'=>'decimal:2',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
