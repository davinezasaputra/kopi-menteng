<?php

namespace App\Domain\Purchasing\Models;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierReturnItem extends Model
{
    protected $fillable = [
        'supplier_return_id','goods_receipt_item_id','product_id',
        'received_quantity','returned_quantity','unit_cost','line_value',
    ];

    protected function casts(): array {
        return [
            'received_quantity'=>'decimal:4',
            'returned_quantity'=>'decimal:4',
            'unit_cost'=>'decimal:4',
            'line_value'=>'decimal:2',
        ];
    }

    public function returnDocument(): BelongsTo { return $this->belongsTo(SupplierReturn::class,'supplier_return_id'); }
    public function goodsReceiptItem(): BelongsTo { return $this->belongsTo(GoodsReceiptItem::class,'goods_receipt_item_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
